<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Support;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

/** A stand-in for a company's identity provider: it holds a key and certificate and builds (signed) SAML answers. */
final class IdpFixture
{
    public const ENTITY_ID = 'https://idp.acme.test/metadata';
    public const SSO_URL = 'https://idp.acme.test/sso';
    private const ASSERTION_NS = 'urn:oasis:names:tc:SAML:2.0:assertion';

    public readonly string $privateKey;
    public readonly string $certificate;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = $key === false ? false : openssl_csr_new(['commonName' => 'idp.acme.test'], $key);
        if (!$key instanceof \OpenSSLAsymmetricKey || !$csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('Could not make a key and a certificate request.');
        }
        $cert = openssl_csr_sign($csr, null, $key, 365);
        if (!$cert instanceof \OpenSSLCertificate || !openssl_pkey_export($key, $privateKey) || !openssl_x509_export($cert, $certificate)) {
            throw new RuntimeException('Could not make a certificate.');
        }
        if (!is_string($privateKey) || !is_string($certificate)) {
            throw new RuntimeException('Could not export the key and certificate.');
        }
        $this->privateKey = $privateKey;
        $this->certificate = $certificate;
    }

    /** The certificate as people paste it into an admin panel. */
    public function certificatePem(): string
    {
        return $this->certificate;
    }

    /**
     * A base64 SAML Response, as the browser would post it.
     *
     * @param array<string, mixed> $o in_response_to, acs, audience, name_id, attributes (name => value), expires_in (seconds),
     *                                sign (bool), signing_key (PEM), issuer, status, tamper (change the name id after signing)
     */
    public function response(array $o): string
    {
        $now = time();
        $acs = $this->text($o, 'acs', 'https://sso.test/saml/1/acs');
        $audience = $this->text($o, 'audience', 'https://sso.test/saml/1/metadata');
        $issuer = $this->text($o, 'issuer', self::ENTITY_ID);
        $inResponseTo = $this->text($o, 'in_response_to', '_request1');
        $nameId = $this->text($o, 'name_id', 'alice-at-acme');
        $status = $this->text($o, 'status', 'urn:oasis:names:tc:SAML:2.0:status:Success');
        $expiresIn = isset($o['expires_in']) && is_int($o['expires_in']) ? $o['expires_in'] : 300;
        $attributes = isset($o['attributes']) && is_array($o['attributes']) ? $o['attributes'] : ['email' => 'alice@acme.com', 'displayName' => 'Alice Acme'];
        $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', $now + $expiresIn);
        $iso = static fn (int $t): string => gmdate('Y-m-d\TH:i:s\Z', $t);

        $attributeXml = '';
        foreach ($attributes as $name => $value) {
            $attributeXml .= '<saml:Attribute Name="' . htmlspecialchars((string) $name) . '"><saml:AttributeValue xsi:type="xs:string">' . htmlspecialchars(is_string($value) ? $value : '') . '</saml:AttributeValue></saml:Attribute>';
        }

        $xml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="' . self::ASSERTION_NS . '" ID="_resp' . bin2hex(random_bytes(4)) . '" Version="2.0" IssueInstant="' . $iso($now) . '" Destination="' . $acs . '" InResponseTo="' . $inResponseTo . '">'
            . '<saml:Issuer>' . $issuer . '</saml:Issuer>'
            . '<samlp:Status><samlp:StatusCode Value="' . $status . '"/></samlp:Status>'
            . '<saml:Assertion xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xs="http://www.w3.org/2001/XMLSchema" ID="_assert' . bin2hex(random_bytes(4)) . '" Version="2.0" IssueInstant="' . $iso($now) . '">'
            . '<saml:Issuer>' . $issuer . '</saml:Issuer>'
            . '<saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">' . htmlspecialchars($nameId) . '</saml:NameID>'
            . '<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="' . $notOnOrAfter . '" Recipient="' . $acs . '" InResponseTo="' . $inResponseTo . '"/></saml:SubjectConfirmation></saml:Subject>'
            . '<saml:Conditions NotBefore="' . $iso($now - 60) . '" NotOnOrAfter="' . $notOnOrAfter . '"><saml:AudienceRestriction><saml:Audience>' . $audience . '</saml:Audience></saml:AudienceRestriction></saml:Conditions>'
            . '<saml:AuthnStatement AuthnInstant="' . $iso($now) . '" SessionIndex="_session1"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>'
            . '<saml:AttributeStatement>' . $attributeXml . '</saml:AttributeStatement>'
            . '</saml:Assertion></samlp:Response>';

        $document = new \DOMDocument();
        $document->loadXML($xml);
        if (($o['sign'] ?? true) === true) {
            $this->sign($document, $this->text($o, 'signing_key', $this->privateKey));
        }
        $out = (string) $document->saveXML();
        if (($o['tamper'] ?? false) === true) {
            $out = str_replace('alice-at-acme', 'admin-at-acme', $out); // changed after signing
        }

        return base64_encode($out);
    }

    /** @param array<string, mixed> $options */
    private function text(array $options, string $key, string $default): string
    {
        return isset($options[$key]) && is_string($options[$key]) ? $options[$key] : $default;
    }

    private function sign(\DOMDocument $document, string $privateKey): void
    {
        $assertion = $document->getElementsByTagNameNS(self::ASSERTION_NS, 'Assertion')->item(0);
        $issuer = $assertion?->getElementsByTagNameNS(self::ASSERTION_NS, 'Issuer')->item(0);
        if (!$assertion instanceof \DOMElement || $issuer === null || $issuer->nextSibling === null) {
            throw new RuntimeException('The answer has no assertion to sign.');
        }
        $signature = new XMLSecurityDSig();
        $signature->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $signature->addReference($assertion, XMLSecurityDSig::SHA256, ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N], ['id_name' => 'ID', 'overwrite' => false]);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey);
        $signature->sign($key);
        $signature->add509Cert($this->certificate, true);
        $signature->insertSignature($assertion, $issuer->nextSibling);
    }
}
