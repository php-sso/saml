<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Unit\ServiceProvider\SpSigningKey;

use PhpSso\Saml\ServiceProvider\SpSigningKey;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(SpSigningKey::class, 'generate')]
#[CoversMethod(SpSigningKey::class, 'fingerprint')]
final class GenerateTest extends TestCase
{
    public function testItMakesAKeyWithACertificateThatBelongsToItAndLastsForTheGivenDays(): void
    {
        // Arrange
        $before = time();

        // Act
        $pair = SpSigningKey::generate('sso.example.com', 100, 2048);

        // Assert
        $key = openssl_pkey_get_private($pair['privateKey']);
        self::assertNotFalse($key);
        self::assertTrue(openssl_x509_check_private_key($pair['certificate'], $key));
        $info = openssl_x509_parse($pair['certificate']);
        self::assertIsArray($info);
        self::assertIsArray($info['subject']);
        self::assertSame('sso.example.com', $info['subject']['CN'] ?? null);
        self::assertEqualsWithDelta($before + 100 * 86400, $info['validTo_time_t'], 120);
        self::assertSame('RSA-SHA256', $info['signatureTypeSN']);
    }

    public function testTheFingerprintIsUpperCaseHexPairsSeparatedByColons(): void
    {
        // Arrange
        $pair = SpSigningKey::generate('sso.example.com', 10, 2048);

        // Act
        $fingerprint = SpSigningKey::fingerprint($pair['certificate']);

        // Assert
        self::assertMatchesRegularExpression('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $fingerprint);
    }
}
