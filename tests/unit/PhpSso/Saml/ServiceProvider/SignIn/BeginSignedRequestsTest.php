<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Unit\ServiceProvider\SignIn;

use PhpSso\Saml\ServiceProvider\SignIn;
use PhpSso\Saml\ServiceProvider\SignInException;
use PhpSso\Saml\ServiceProvider\SpSigningKey;
use PhpSso\Saml\Tests\Support\Support;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(SignIn::class, 'begin')]
#[CoversMethod(SignIn::class, 'metadataXml')]
final class BeginSignedRequestsTest extends TestCase
{
    private const ALGORITHM = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    /** @var array{privateKey: string, certificate: string} */
    private static array $pair;

    public static function setUpBeforeClass(): void
    {
        self::$pair = SpSigningKey::generate('sso.test', 30, 2048);
    }

    public function testARequestToAConnectionThatWantsItIsSignedWithOurKey(): void
    {
        // Arrange
        $app = new Support(signRequests: true, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate']));

        // Act
        $started = $app->service->begin($app->id, '/account');

        // Assert
        $query = (string) parse_url($started->url, PHP_URL_QUERY);
        self::assertTrue($this->signatureVerifies($query, self::$pair['certificate']));
        self::assertSame(self::ALGORITHM, $this->param($query, 'SigAlg'));
    }

    public function testAChangedRequestNoLongerVerifies(): void
    {
        // Arrange
        $app = new Support(signRequests: true, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate']));
        $query = (string) parse_url($app->service->begin($app->id, null)->url, PHP_URL_QUERY);
        $other = (string) parse_url($app->service->begin($app->id, null)->url, PHP_URL_QUERY);

        // Act
        $swapped = preg_replace('/^SAMLRequest=[^&]*/', 'SAMLRequest=' . rawurlencode($this->param($other, 'SAMLRequest')), $query);

        // Assert
        self::assertFalse($this->signatureVerifies((string) $swapped, self::$pair['certificate']));
    }

    public function testAnotherKeyDoesNotVerifyTheSignature(): void
    {
        // Arrange
        $app = new Support(signRequests: true, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate']));
        $other = SpSigningKey::generate('someone-else', 30, 2048);

        // Act
        $query = (string) parse_url($app->service->begin($app->id, null)->url, PHP_URL_QUERY);

        // Assert
        self::assertFalse($this->signatureVerifies($query, $other['certificate']));
    }

    public function testAConnectionThatDoesNotWantSignedRequestsGetsAnUnsignedOne(): void
    {
        // Arrange
        $app = new Support(signRequests: false, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate']));

        // Act
        $started = $app->service->begin($app->id, null);

        // Assert
        parse_str((string) parse_url($started->url, PHP_URL_QUERY), $parts);
        self::assertArrayNotHasKey('Signature', $parts);
        self::assertArrayNotHasKey('SigAlg', $parts);
    }

    public function testASignedConnectionWithoutAKeyIsRefusedInsteadOfSendingAnUnsignedRequest(): void
    {
        // Arrange
        $app = new Support(signRequests: true);
        $this->expectException(SignInException::class);

        // Act
        $app->service->begin($app->id, null);

        // Assert (the exception above)
    }

    public function testTheMetadataSaysRequestsAreSignedAndPublishesTheCertificateAndTheNextOne(): void
    {
        // Arrange
        $next = SpSigningKey::generate('sso.test', 30, 2048);
        $app = new Support(signRequests: true, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate'], $next['certificate']));

        // Act
        $xml = $app->service->metadataXml($app->id);

        // Assert
        self::assertStringContainsString('AuthnRequestsSigned="true"', $xml);
        self::assertSame(2, substr_count($xml, '<md:KeyDescriptor use="signing">'));
        self::assertStringContainsString($this->body(self::$pair['certificate']), $xml);
        self::assertStringContainsString($this->body($next['certificate']), $xml);
    }

    public function testTheMetadataOfAConnectionThatDoesNotSignSaysSo(): void
    {
        // Arrange
        $app = new Support(signRequests: false, signingKey: new SpSigningKey(self::$pair['privateKey'], self::$pair['certificate']));

        // Act
        $xml = $app->service->metadataXml($app->id);

        // Assert
        self::assertStringContainsString('AuthnRequestsSigned="false"', $xml);
    }

    /** SAML's redirect binding signs the query string exactly as sent: SAMLRequest, RelayState, SigAlg. */
    private function signatureVerifies(string $query, string $certificate): bool
    {
        preg_match('/^(SAMLRequest=[^&]*(?:&RelayState=[^&]*)?&SigAlg=[^&]*)/', $query, $m);

        return ($m[1] ?? '') !== '' && openssl_verify($m[1], (string) base64_decode($this->param($query, 'Signature'), true), $certificate, OPENSSL_ALGO_SHA256) === 1;
    }

    private function param(string $query, string $name): string
    {
        parse_str($query, $parts);
        $value = $parts[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }

    private function body(string $pem): string
    {
        return str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"], '', $pem);
    }
}
