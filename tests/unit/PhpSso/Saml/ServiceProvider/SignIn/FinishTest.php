<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Unit\ServiceProvider\SignIn;

use PhpSso\Saml\ServiceProvider\SignIn;
use PhpSso\Saml\ServiceProvider\SignInException;
use PhpSso\Saml\Tests\Support\IdpFixture;
use PhpSso\Saml\Tests\Support\Support;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversMethod(SignIn::class, 'finish')]
final class FinishTest extends TestCase
{
    public function testASignedAnswerToOurRequestSaysWhoSignedIn(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();

        // Act
        $result = $app->service->finish($app->id, $app->answer(), 'binding-token');

        // Assert
        $identity = $result->identity;
        self::assertSame([$app->id, 'alice-at-acme', 'alice@acme.com', true, 'Alice Acme'], [$identity->connectionId, $identity->nameId, $identity->email, $identity->emailVerified, $identity->name]);
        self::assertSame('/account', $result->returnTo);
    }

    public function testAnAddressOutsideTheClaimedDomainsIsNotVouchedFor(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $answer = $app->answer(['attributes' => ['email' => 'ceo@other-company.com']]);

        // Act
        $identity = $app->service->finish($app->id, $answer, 'binding-token')->identity;

        // Assert
        self::assertSame('ceo@other-company.com', $identity->email);
        self::assertFalse($identity->emailVerified);
    }

    public function testAnySeveralClaimedDomainsCanVouch(): void
    {
        // Arrange
        $app = new Support('https://sso.test', true, ['acme.com', 'acme.org']);
        $app->sent();
        $answer = $app->answer(['attributes' => ['email' => 'Alice@ACME.org']]);

        // Act
        $identity = $app->service->finish($app->id, $answer, 'binding-token')->identity;

        // Assert
        self::assertSame(['alice@acme.org', true], [$identity->email, $identity->emailVerified]);
    }

    public function testTheNameCanBeBuiltFromFirstAndLastName(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $answer = $app->answer(['attributes' => ['email' => 'alice@acme.com', 'givenName' => 'Alice', 'sn' => 'Acme']]);

        // Act
        $identity = $app->service->finish($app->id, $answer, 'binding-token')->identity;

        // Assert
        self::assertSame('Alice Acme', $identity->name);
    }

    public function testAnAnswerWithNoNameAtAllLeavesTheNameEmpty(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $answer = $app->answer(['attributes' => ['email' => 'alice@acme.com']]);

        // Act
        $identity = $app->service->finish($app->id, $answer, 'binding-token')->identity;

        // Assert
        self::assertNull($identity->name);
    }

    public function testAnEmailNobodyCanReadIsLeftOut(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $answer = $app->answer(['attributes' => ['email' => 'not an address', 'displayName' => 'Alice']]);

        // Act
        $identity = $app->service->finish($app->id, $answer, 'binding-token')->identity;

        // Assert
        self::assertSame([null, false], [$identity->email, $identity->emailVerified]);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function refusedAnswers(): iterable
    {
        yield 'changed after it was signed' => [['tamper' => true]];
        yield 'not signed at all' => [['sign' => false]];
        yield 'expired' => [['expires_in' => -3600]];
        yield 'meant for another application' => [['audience' => 'https://other-app.test/metadata']];
        yield 'sent to another address' => [['acs' => 'https://evil.test/saml/1/acs']];
        yield 'from another issuer' => [['issuer' => 'https://evil.test/idp']];
        yield 'a failed sign-in' => [['status' => 'urn:oasis:names:tc:SAML:2.0:status:AuthnFailed']];
    }

    /** @param array<string, mixed> $options */
    #[DataProvider('refusedAnswers')]
    public function testAnAnswerThatIsNotRightIsRefused(array $options): void
    {
        // Arrange
        $app = new Support();
        $app->sent();

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer($options), 'binding-token');

        // Assert
        try {
            $refuse();
            self::fail('The answer was accepted.');
        } catch (SignInException $e) {
            self::assertStringContainsString('SAML response refused', $e->detail());
        }
    }

    public function testAnAnswerSignedWithAnotherKeyIsRefused(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $other = new IdpFixture();
        $answer = $app->answer(['signing_key' => $other->privateKey]);

        // Act
        $refuse = fn () => $app->service->finish($app->id, $answer, 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testAnAnswerNobodyAskedForIsRefused(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer(['in_response_to' => '_never_sent']), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $this->expectExceptionMessage('expired or was not started here');
        $refuse();
    }

    public function testTheSameAnswerCannotBeUsedTwice(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $answer = $app->answer();
        $app->service->finish($app->id, $answer, 'binding-token');

        // Act
        $replay = fn () => $app->service->finish($app->id, $answer, 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $replay();
    }

    public function testAnAnswerCarriedByAnotherBrowserIsRefused(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer(), 'someone-elses-token');

        // Assert
        $this->expectException(SignInException::class);
        $this->expectExceptionMessage('not started in this browser');
        $refuse();
    }

    public function testWithoutTheBrowserCookieAnAnswerIsRefusedOverHttps(): void
    {
        // Arrange
        $app = new Support('https://sso.test');
        $app->sent();

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer(), null);

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testWithoutTheBrowserCookieAnAnswerIsAcceptedOverPlainHttpForDevelopment(): void
    {
        // Arrange
        $app = new Support('http://sso.test');
        $app->sent();

        // Act
        $result = $app->service->finish($app->id, $app->answer(), null);

        // Assert
        self::assertSame('alice-at-acme', $result->identity->nameId);
    }

    public function testAnAnswerToARequestOfAnotherConnectionIsRefused(): void
    {
        // Arrange
        $app = new Support();
        $other = $app->connections->add('https://beta.test', 'https://beta.test/sso', $app->idp->certificatePem(), true, ['beta.com']);
        $app->requests->remember('_request1', $other, hash('sha256', 'binding-token'), null);

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer(), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testASwitchedOffConnectionSignsNobodyIn(): void
    {
        // Arrange
        $app = new Support('https://sso.test', false);
        $app->sent();

        // Act
        $refuse = fn () => $app->service->finish($app->id, $app->answer(), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testAnUnknownConnectionSignsNobodyIn(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();

        // Act
        $refuse = fn () => $app->service->finish(999, $app->answer(), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testAFailedAnswerLeavesTheRequestEnvironmentAsItWas(): void
    {
        // Arrange
        $app = new Support();
        $app->sent();
        $_SERVER['REQUEST_URI'] = '/before';
        $_SERVER['QUERY_STRING'] = 'a=b';

        // Act
        try {
            $app->service->finish($app->id, $app->answer(['tamper' => true]), 'binding-token');
        } catch (SignInException) {
            // expected
        }

        // Assert
        self::assertSame(['/before', 'a=b'], [$_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']]);
        unset($_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']);
    }

    public function testSomethingThatIsNotXmlIsRefused(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $refuse = fn () => $app->service->finish($app->id, base64_encode('not xml at all'), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testSomethingThatIsNotBase64IsRefused(): void
    {
        // Arrange
        $app = new Support();

        // Act
        $refuse = fn () => $app->service->finish($app->id, '%%% not base64 %%%', 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }

    public function testXmlWithADoctypeIsRefusedBeforeAnythingIsResolved(): void
    {
        // Arrange
        $app = new Support();
        $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" InResponseTo="_request1">&x;</samlp:Response>';

        // Act
        $refuse = fn () => $app->service->finish($app->id, base64_encode($xml), 'binding-token');

        // Assert
        $this->expectException(SignInException::class);
        $refuse();
    }
}
