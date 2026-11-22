<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Unit\ServiceProvider\SpSigningKey;

use InvalidArgumentException;
use PhpSso\Saml\ServiceProvider\SpSigningKey;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(SpSigningKey::class, '__construct')]
final class __constructTest extends TestCase
{
    public function testItAcceptsAKeyAndItsCertificate(): void
    {
        // Arrange
        $pair = SpSigningKey::generate('sso.test', 10, 2048);

        // Act
        $key = new SpSigningKey($pair['privateKey'], $pair['certificate']);

        // Assert
        self::assertSame($pair['certificate'], $key->certificate);
        self::assertNull($key->nextCertificate);
    }

    public function testItRefusesACertificateThatIsNotForTheKey(): void
    {
        // Arrange
        $a = SpSigningKey::generate('a', 10, 2048);
        $b = SpSigningKey::generate('b', 10, 2048);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        // Act
        new SpSigningKey($a['privateKey'], $b['certificate']);

        // Assert (the exception above)
    }

    public function testItRefusesAKeyThatIsNotAKey(): void
    {
        // Arrange
        $pair = SpSigningKey::generate('a', 10, 2048);
        $this->expectException(InvalidArgumentException::class);

        // Act
        new SpSigningKey('not a key', $pair['certificate']);

        // Assert (the exception above)
    }

    public function testItRefusesANextCertificateThatIsNotACertificate(): void
    {
        // Arrange
        $pair = SpSigningKey::generate('a', 10, 2048);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('next');

        // Act
        new SpSigningKey($pair['privateKey'], $pair['certificate'], 'nonsense');

        // Assert (the exception above)
    }
}
