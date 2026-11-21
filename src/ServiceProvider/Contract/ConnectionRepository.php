<?php

declare(strict_types=1);

namespace PhpSso\Saml\ServiceProvider\Contract;

use PhpSso\Saml\ServiceProvider\IdpConnection;

/** The identity providers your application has been set up with. */
interface ConnectionRepository
{
    public function find(int $id): ?IdpConnection;
}
