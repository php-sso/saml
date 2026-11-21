<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Support;

use PhpSso\Saml\ServiceProvider\Contract\RequestStore;
use PhpSso\Saml\ServiceProvider\PendingRequest;

final class InMemoryRequests implements RequestStore
{
    /** @var array<string, PendingRequest> */
    private array $requests = [];

    public function remember(string $requestId, int $connectionId, string $bindingHash, ?string $returnTo): void
    {
        $this->requests[$requestId] = new PendingRequest($connectionId, $bindingHash, $returnTo);
    }

    public function consume(string $requestId): ?PendingRequest
    {
        $request = $this->requests[$requestId] ?? null;
        unset($this->requests[$requestId]);

        return $request;
    }

    /** How many requests are still waiting for an answer. */
    public function waiting(): int
    {
        return count($this->requests);
    }
}
