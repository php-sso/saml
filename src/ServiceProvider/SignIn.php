<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use PhpSso\Saml\ServiceProvider\Contract\ConnectionRepository;
use PhpSso\Saml\ServiceProvider\Contract\RequestStore;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Signing people in through a company's identity provider, with `onelogin/php-saml` doing the XML and signature work. We are
 * the service provider: a request goes out, one signed answer comes back, and only that answer (for a request we sent, once,
 * to the browser that started it) can sign someone in. Answers nobody asked for are refused.
 */
final class SignIn
{
    /** Where an attribute with the person's address, name, first and last name can hide. */
    private const EMAIL_ATTRIBUTES = ['email', 'mail', 'emailaddress', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress', 'urn:oid:0.9.2342.19200300.100.1.3'];
    private const NAME_ATTRIBUTES = ['displayname', 'name', 'cn', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name', 'urn:oid:2.16.840.1.113730.3.1.241'];
    private const FIRST_NAME_ATTRIBUTES = ['givenname', 'firstname', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname', 'urn:oid:2.5.4.42'];
    private const LAST_NAME_ATTRIBUTES = ['surname', 'sn', 'lastname', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname', 'urn:oid:2.5.4.4'];

    /**
     * @param string $baseUrl the address people reach the application at, which the entity id and reply address are built on
     * @param SpSigningKey|null $signingKey signs requests to the connections that ask for it, and its certificate is in the metadata
     */
    public function __construct(
        private ConnectionRepository $connections,
        private RequestStore $requests,
        private string $baseUrl,
        private ?SpSigningKey $signingKey = null,
    ) {
    }

    /** Our identifier at the company's provider, and where its metadata is. */
    public function entityId(int $connectionId): string
    {
        return rtrim($this->baseUrl, '/') . '/saml/' . $connectionId . '/metadata';
    }

    /** Where the provider sends the signed answer. */
    public function acsUrl(int $connectionId): string
    {
        return rtrim($this->baseUrl, '/') . '/saml/' . $connectionId . '/acs';
    }

    /** Whether the address people sign in at is https, which decides how the browser is bound to a request. */
    public function secure(): bool
    {
        return str_starts_with(strtolower($this->baseUrl), 'https://');
    }

    /** What the company's administrator needs to set us up as an application (our entity id, answer address and metadata). */
    public function metadataXml(int $connectionId): string
    {
        $connection = $this->connections->find($connectionId);
        $settings = new Settings($this->settingsArray($connectionId, null, $connection !== null && $connection->signRequests), true);

        return $settings->getSPMetadata();
    }

    /**
     * Starts a sign-in at the company's provider.
     *
     * @param string|null $returnTo where to send the person afterwards; handed back by finish()
     * @throws SignInException
     */
    public function begin(int $connectionId, ?string $returnTo): StartedSignIn
    {
        $connection = $this->connections->find($connectionId);
        if ($connection === null) {
            throw new SignInException('Signing in through your organisation is not available.', "No connection {$connectionId}.");
        }
        if (!$connection->enabled) {
            throw new SignInException('Signing in through your organisation is not available.', "Connection {$connectionId} is switched off.");
        }
        if ($connection->signRequests && $this->signingKey === null) {
            throw new SignInException('Signing in through your organisation is not set up correctly. Ask an administrator.', "Connection {$connectionId} must sign its requests, but there is no signing key.");
        }
        try {
            $auth = new Auth($this->settingsArray($connectionId, $connection, $connection->signRequests));
            $url = $auth->login(bin2hex(random_bytes(8)), [], false, false, true);
            $requestId = (string) $auth->getLastRequestID();
        } catch (\Throwable $e) {
            throw new SignInException('Signing in through your organisation is not set up correctly. Ask an administrator.', 'Could not build the request: ' . $e->getMessage());
        }
        if (!is_string($url)) {
            throw new SignInException('Signing in through your organisation is not set up correctly. Ask an administrator.', 'Could not build the request.');
        }
        $binding = bin2hex(random_bytes(16));
        $this->requests->remember($requestId, $connectionId, hash('sha256', $binding), $returnTo);

        return new StartedSignIn($url, $binding);
    }

    /**
     * Checks the provider's answer and says who signed in.
     *
     * @param string|null $binding the cookie the browser got when it started
     * @throws SignInException
     */
    public function finish(int $connectionId, string $samlResponse, ?string $binding): CompletedSignIn
    {
        $connection = $this->connections->find($connectionId);
        if ($connection === null || !$connection->enabled) {
            throw new SignInException('Signing in through your organisation is not available.', "Answer for unknown or switched off connection {$connectionId}.");
        }
        $xml = base64_decode($samlResponse, true);
        try {
            $document = $xml === false ? false : Utils::loadXML(new \DOMDocument(), $xml); // refuses a DOCTYPE or entities by throwing
        } catch (\Throwable $e) {
            throw new SignInException('We could not read the answer from your organisation. Please try again.', 'The SAML response was refused as XML: ' . $e->getMessage());
        }
        if (!$document instanceof \DOMDocument || $document->documentElement === null) {
            throw new SignInException('We could not read the answer from your organisation. Please try again.', 'The SAML response is not (safe) XML.');
        }

        // Only used to find the request this answers: nothing in the answer is trusted until it has been validated below.
        $inResponseTo = $document->documentElement->getAttribute('InResponseTo');
        $request = $inResponseTo === '' ? null : $this->requests->consume($inResponseTo);
        if ($request === null || $request->connectionId !== $connectionId) {
            throw new SignInException('That sign-in attempt expired or was not started here. Please try again.', 'The SAML response answers no request of ours (unsolicited, replayed, expired or for another connection).');
        }
        // The answer is a POST from another site, so only a cookie set for exactly this purpose can say it is the same browser.
        // Over plain http (development) browsers do not send it back, so then it can only be checked when present.
        if ($binding !== null ? !hash_equals($request->bindingHash, hash('sha256', $binding)) : $this->secure()) {
            throw new SignInException('That sign-in attempt was not started in this browser. Please try again.', 'The SAML response came from a browser that did not start the request.');
        }

        // The library compares the answer's Destination with the address it was received at, which it reads from the request.
        // This is only ever called for the reply address of this connection, so say so instead of trusting what a proxy made of the path.
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $previousQuery = $_SERVER['QUERY_STRING'] ?? null;
        $_SERVER['REQUEST_URI'] = (string) parse_url($this->acsUrl($connectionId), PHP_URL_PATH);
        unset($_SERVER['QUERY_STRING']);
        try {
            $response = new Response(new Settings($this->settingsArray($connectionId, $connection)), $samlResponse);
            if (!$response->isValid($inResponseTo)) {
                throw new SignInException('We could not sign you in through your organisation.', 'SAML response refused: ' . (string) $response->getError());
            }
            $nameId = (string) $response->getNameId();
            /** @var array<string, list<string>> $attributes */
            $attributes = $response->getAttributes();
            $nameIdFormat = $response->getNameIdFormat();
        } catch (SignInException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SignInException('We could not sign you in through your organisation.', 'SAML response failed: ' . $e->getMessage());
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
            if ($previousQuery !== null) {
                $_SERVER['QUERY_STRING'] = $previousQuery;
            }
        }
        if ($nameId === '') {
            throw new SignInException('We could not sign you in through your organisation.', 'The assertion has no NameID.');
        }

        $email = $this->email($attributes, $nameId, is_string($nameIdFormat) ? $nameIdFormat : null);
        // The provider is the authority for the domains the connection claims, and for no others: a company must not be
        // able to vouch for an address at someone else's domain.
        $verified = $email !== null && in_array(substr($email, (int) strrpos($email, '@') + 1), $connection->domains, true);

        return new CompletedSignIn(new SamlIdentity($connectionId, $nameId, $email, $verified, $this->name($attributes)), $request->returnTo);
    }

    /** @param array<string, list<string>> $attributes */
    private function email(array $attributes, string $nameId, ?string $nameIdFormat): ?string
    {
        $candidate = $this->first($attributes, self::EMAIL_ATTRIBUTES);
        if ($candidate === null && $nameIdFormat === Constants::NAMEID_EMAIL_ADDRESS) {
            $candidate = $nameId;
        }
        $candidate = $candidate === null ? null : mb_strtolower(trim($candidate));

        return $candidate !== null && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false ? $candidate : null;
    }

    /** @param array<string, list<string>> $attributes */
    private function name(array $attributes): ?string
    {
        $name = $this->first($attributes, self::NAME_ATTRIBUTES);
        if ($name === null) {
            $name = trim(($this->first($attributes, self::FIRST_NAME_ATTRIBUTES) ?? '') . ' ' . ($this->first($attributes, self::LAST_NAME_ATTRIBUTES) ?? ''));
        }

        return $name === '' ? null : $name;
    }

    /**
     * The first value of the first of these attributes that has one (attribute names compared without case).
     *
     * @param array<string, list<string>> $attributes
     * @param list<string> $names
     */
    private function first(array $attributes, array $names): ?string
    {
        $lower = [];
        foreach ($attributes as $key => $values) {
            $lower[strtolower((string) $key)] = $values;
        }
        foreach ($names as $name) {
            $value = $lower[$name][0] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param IdpConnection|null $connection null when only our own side is needed (metadata)
     * @param bool $signRequests whether requests are signed, which the metadata then says
     * @return array<string, mixed>
     */
    private function settingsArray(int $connectionId, ?IdpConnection $connection, bool $signRequests = false): array
    {
        $settings = [
            'strict' => true,
            'debug' => false,
            'baseurl' => rtrim($this->baseUrl, '/'),
            'sp' => [
                'entityId' => $this->entityId($connectionId),
                'assertionConsumerService' => ['url' => $this->acsUrl($connectionId), 'binding' => Constants::BINDING_HTTP_POST],
                'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
            ],
            'security' => [
                'authnRequestsSigned' => $signRequests && $this->signingKey !== null,
                'signatureAlgorithm' => XMLSecurityKey::RSA_SHA256,
                'digestAlgorithm' => XMLSecurityDSig::SHA256,
                // Either the answer or the assertion must be signed (the library insists on at least one), and the signature must be the provider's.
                'wantMessagesSigned' => false,
                'wantAssertionsSigned' => false,
                'wantAssertionsEncrypted' => false,
                'wantNameId' => true,
                'wantXMLValidation' => true,
                'requestedAuthnContext' => false,
                'rejectUnsolicitedResponsesWithInResponseTo' => true,
                'destinationStrictlyMatches' => true,
                'relaxDestinationValidation' => false,
            ],
        ];
        if ($this->signingKey !== null) {
            $settings['sp']['x509cert'] = $this->signingKey->certificate;
            $settings['sp']['privateKey'] = $this->signingKey->privateKey;
            if ($this->signingKey->nextCertificate !== null) {
                $settings['sp']['x509certNew'] = $this->signingKey->nextCertificate;
            }
        }
        if ($connection !== null) {
            $settings['idp'] = [
                'entityId' => $connection->entityId,
                'singleSignOnService' => ['url' => $connection->ssoUrl, 'binding' => Constants::BINDING_HTTP_REDIRECT],
                'x509cert' => $connection->certificate,
            ];
        }

        return $settings;
    }
}
