<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider\Contract;

use PhpSso\Saml\ServiceProvider\PendingRequest;

/**
 * The sign-in requests that have been sent and not answered. The answer comes back as a POST from another site, which a
 * session cookie does not survive, so what is needed to check it must live here, not in the session: one record per
 * request, used once, and only for a short while.
 */
interface RequestStore
{
    /** @param string $bindingHash the SHA-256 of the secret given to the browser that started the request; keep it as it is */
    public function remember(string $requestId, int $connectionId, string $bindingHash, ?string $returnTo): void;

    /** Takes the request out: it works once, and only while it is fresh (ten minutes is plenty). Null when there is none. */
    public function consume(string $requestId): ?PendingRequest;
}
