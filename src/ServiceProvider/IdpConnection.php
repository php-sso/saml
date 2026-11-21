<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

/** A company's identity provider, as signing in through it needs to know it. */
final class IdpConnection
{
    /**
     * @param string $entityId the provider's own identifier
     * @param string $ssoUrl where the browser is sent to sign in (HTTP-Redirect binding)
     * @param string $certificate the provider's signing certificate, PEM
     * @param list<string> $domains the lower-case email domains the provider may vouch for
     */
    public function __construct(
        public readonly int $id,
        public readonly bool $enabled,
        public readonly string $entityId,
        public readonly string $ssoUrl,
        public readonly string $certificate,
        public readonly array $domains,
    ) {
    }
}
