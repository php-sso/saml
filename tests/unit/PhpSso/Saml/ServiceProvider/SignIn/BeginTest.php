<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Unit\ServiceProvider\SignIn;

use PhpSso\Saml\ServiceProvider\SignIn;
use PhpSso\Saml\ServiceProvider\SignInException;
use PhpSso\Saml\Tests\Support\IdpFixture;
use PhpSso\Saml\Tests\Support\Support;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(SignIn::class, 'begin')]
#[CoversMethod(SignIn::class, 'metadataXml')]
#[CoversMethod(SignIn::class, 'entityId')]
#[CoversMethod(SignIn::class, 'acsUrl')]
#[CoversMethod(SignIn::class, 'secure')]
final class BeginTest extends TestCase
{
    public function testTheBrowserIsSentToTheirProviderWithARequestWeRemember(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $started = $app->service->begin($app->id, '/account');

        // Assert
        self::assertStringStartsWith(IdpFixture::SSO_URL . '?SAMLRequest=', $started->url);
        self::assertSame(32, strlen($started->binding));
        parse_str((string) parse_url($started->url, PHP_URL_QUERY), $query);
        self::assertIsString($query['SAMLRequest']);
        $request = (string) gzinflate((string) base64_decode($query['SAMLRequest'], true));
        self::assertStringContainsString('AssertionConsumerServiceURL="https://sso.test/saml/' . $app->id . '/acs"', $request);
        self::assertStringContainsString('<saml:Issuer', $request);
        self::assertSame(1, $app->requests->waiting());
    }

    public function testOnlyTheHashOfTheBrowserSecretIsKeptAndTheReturnAddressComesBack(): void
    {
        // Arrange
        $app = new Support();
        $started = $app->service->begin($app->id, '/account');
        parse_str((string) parse_url($started->url, PHP_URL_QUERY), $query);
        self::assertIsString($query['SAMLRequest']);
        self::assertSame(1, preg_match('/ ID="([^"]+)"/', (string) gzinflate((string) base64_decode($query['SAMLRequest'], true)), $id));

        // Act
        $pending = $app->requests->consume($id[1]);

        // Assert
        self::assertNotNull($pending);
        self::assertSame([$app->id, hash('sha256', $started->binding), '/account'], [$pending->connectionId, $pending->bindingHash, $pending->returnTo]);
    }

    public function testASwitchedOffConnectionDoesNotStart(): void
    {
        // Arrange
        $app = new Support('https://sso.test', false);

        // Act
        $start = fn () => $app->service->begin($app->id, null);

        // Assert
        $this->expectException(SignInException::class);
        $start();
    }

    public function testAnUnknownConnectionDoesNotStart(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $start = fn () => $app->service->begin(999, null);

        // Assert
        try {
            $start();
            self::fail('A sign-in started for a connection that does not exist.');
        } catch (SignInException $e) {
            self::assertSame('No connection 999.', $e->detail());
        }
    }

    public function testTheMetadataNamesUsAndOurReplyAddress(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $xml = $app->service->metadataXml($app->id);

        // Assert
        self::assertStringContainsString('entityID="https://sso.test/saml/' . $app->id . '/metadata"', $xml);
        self::assertStringContainsString('Location="https://sso.test/saml/' . $app->id . '/acs"', $xml);
    }

    public function testAHttpsAddressIsSecureAndTrailingSlashesAreIgnored(): void
    {
        // Arrange
        $https = new Support('https://sso.test/');
        $http = new Support('http://sso.test');

        // Act
        $secure = [$https->service->secure(), $http->service->secure()];

        // Assert
        self::assertSame([true, false], $secure);
        self::assertSame('https://sso.test/saml/1/acs', $https->service->acsUrl(1));
    }
}
