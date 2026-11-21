<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

/** A sign-in the provider's signed answer vouches for. */
final class CompletedSignIn
{
    /** @param string|null $returnTo where the person was heading when they started, as passed to begin() */
    public function __construct(
        public readonly SamlIdentity $identity,
        public readonly ?string $returnTo,
    ) {
    }
}
