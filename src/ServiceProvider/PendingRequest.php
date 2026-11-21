<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

/** A sign-in request we sent and are waiting for an answer to. */
final class PendingRequest
{
    public function __construct(
        public readonly int $connectionId,
        public readonly string $bindingHash,
        public readonly ?string $returnTo,
    ) {
    }
}
