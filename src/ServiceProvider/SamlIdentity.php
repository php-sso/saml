<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

/** Who a provider says signed in. `nameId` is the provider's permanent id for them: never match people on the email alone. */
final class SamlIdentity
{
    /** @param bool $emailVerified true only when the address is at a domain the connection claims */
    public function __construct(
        public readonly int $connectionId,
        public readonly string $nameId,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
    ) {
    }
}
