<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider;

/** A sign-in that has been started: where to send the browser, and the secret to give that browser as a cookie. */
final class StartedSignIn
{
    public function __construct(
        public readonly string $url,
        public readonly string $binding,
    ) {
    }
}
