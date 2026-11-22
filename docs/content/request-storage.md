---
title: "Request storage"
weight: 6
group: "Build it"
lead: "Where requests wait for their answer, and why it is not the session."
---

When a sign-in starts, the package remembers the request. When the answer arrives, it must find that request again. This is
what makes sure an answer is accepted **only for a request you sent, once, and only in the browser that started it**.

## Why not the session

The answer comes back as a POST from another site, and a session cookie does not survive that. So what is needed to check
the answer must live in a store you control, not in the session.

## The contract

```php
interface RequestStore
{
    public function remember(string $requestId, int $connectionId, string $bindingHash, ?string $returnTo): void;

    public function consume(string $requestId): ?PendingRequest;
}
```

| Rule | Why |
| --- | --- |
| `remember()` keeps the `bindingHash` exactly as given | It is already a SHA-256 hash, compared with `hash_equals()`. Hashing it again or changing its case would refuse every answer. |
| `consume()` works **once** | A replayed answer must find nothing. |
| `consume()` returns `null` once the request is **stale** | Ten minutes is plenty. A person who takes longer starts again. |
| `consume()` returns `null` for an unknown id | Unsolicited answers must find nothing. |

## A store to start from

```php
use PhpSso\Saml\ServiceProvider\Contract\RequestStore;
use PhpSso\Saml\ServiceProvider\PendingRequest;

final class PdoRequests implements RequestStore
{
    private const LIFETIME = 600; // seconds

    public function __construct(private \PDO $pdo)
    {
    }

    public function remember(string $requestId, int $connectionId, string $bindingHash, ?string $returnTo): void
    {
        // Clear what nobody came back for, so the table stays small.
        $this->pdo->prepare('DELETE FROM saml_requests WHERE created_at < ?')->execute([time() - self::LIFETIME]);

        $this->pdo
            ->prepare('INSERT INTO saml_requests (request_id, connection_id, binding_hash, return_to, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$requestId, $connectionId, $bindingHash, $returnTo, time()]);
    }

    public function consume(string $requestId): ?PendingRequest
    {
        $select = $this->pdo->prepare(
            'SELECT connection_id, binding_hash, return_to, created_at FROM saml_requests WHERE request_id = ?'
        );
        $select->execute([$requestId]);
        $row = $select->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        // Only the one that really removes the row may use it: two answers at the same moment cannot both win.
        $delete = $this->pdo->prepare('DELETE FROM saml_requests WHERE request_id = ?');
        $delete->execute([$requestId]);
        if ($delete->rowCount() !== 1 || (int) $row['created_at'] < time() - self::LIFETIME) {
            return null;
        }

        return new PendingRequest((int) $row['connection_id'], (string) $row['binding_hash'], $row['return_to']);
    }
}
```

```sql
CREATE TABLE saml_requests (
    request_id    VARCHAR(64) PRIMARY KEY,
    connection_id INTEGER NOT NULL,
    binding_hash  CHAR(64) NOT NULL,
    return_to     TEXT NULL,
    created_at    INTEGER NOT NULL
);
```

## Pitfalls

- **Not using the session.** It looks easier and it fails exactly when it matters: after the cross-site POST.
- **Reading without deleting.** If `consume()` does not remove the row, the same answer can sign someone in twice.
- **Checking freshness in `remember()` only.** Check it when the request is taken out.
- **A cache that evicts early.** If the store loses a request before the person comes back, they are refused and must start
  again. A cache is fine if it keeps entries for the full ten minutes.
- **Several servers.** The store must be shared. Start on one server and answer on another, and a per-server store finds nothing.

## For tests

An in-memory store is enough: see `tests/support/InMemoryRequests.php` in the repository. It keeps requests in an array and
removes one when it is taken.
