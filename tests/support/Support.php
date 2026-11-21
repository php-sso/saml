<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Support;

use PhpSso\Saml\ServiceProvider\SignIn;

/** A company connection (acme.com) with a fake identity provider, and the service that talks to it. */
final class Support
{
    public readonly IdpFixture $idp;
    public readonly InMemoryConnections $connections;
    public readonly InMemoryRequests $requests;
    public readonly SignIn $service;
    public readonly int $id;

    /** @param list<string> $domains */
    public function __construct(string $baseUrl = 'https://sso.test', bool $enabled = true, array $domains = ['acme.com'])
    {
        $this->idp = new IdpFixture();
        $this->connections = new InMemoryConnections();
        $this->requests = new InMemoryRequests();
        $this->service = new SignIn($this->connections, $this->requests, $baseUrl);
        $this->id = $this->connections->add(IdpFixture::ENTITY_ID, IdpFixture::SSO_URL, $this->idp->certificatePem(), $enabled, $domains);
    }

    /** A request we sent and have not had an answer to. */
    public function sent(string $requestId = '_request1', string $binding = 'binding-token', ?string $returnTo = '/account'): void
    {
        $this->requests->remember($requestId, $this->id, hash('sha256', $binding), $returnTo);
    }

    /** @param array<string, mixed> $options see IdpFixture::response() */
    public function answer(array $options = []): string
    {
        return $this->idp->response($options + ['acs' => $this->service->acsUrl($this->id), 'audience' => $this->service->entityId($this->id)]);
    }
}
