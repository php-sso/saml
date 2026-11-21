<?php

declare(strict_types=1);

namespace PhpSso\Saml\Tests\Support;

use PhpSso\Saml\ServiceProvider\Contract\ConnectionRepository;
use PhpSso\Saml\ServiceProvider\IdpConnection;

final class InMemoryConnections implements ConnectionRepository
{
    /** @var array<int, IdpConnection> */
    private array $connections = [];

    /**
     * @param list<string> $domains
     * @return int the new connection's id
     */
    public function add(string $entityId, string $ssoUrl, string $certificate, bool $enabled = true, array $domains = ['acme.com']): int
    {
        $id = count($this->connections) + 1;
        $this->connections[$id] = new IdpConnection($id, $enabled, $entityId, $ssoUrl, $certificate, $domains);

        return $id;
    }

    public function find(int $id): ?IdpConnection
    {
        return $this->connections[$id] ?? null;
    }
}
