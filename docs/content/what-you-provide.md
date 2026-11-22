---
title: "What you provide"
weight: 3
group: "Build it"
lead: "The two interfaces your application implements."
---

| Interface | You provide |
| --- | --- |
| `ConnectionRepository` | The identity providers you have been set up with (`IdpConnection`): entity id, sign-on URL, signing certificate, on or off, and the email domains it may vouch for. |
| `RequestStore` | The requests waiting for an answer: remember one (with a hash of the browser secret), and hand it back once, only while it is fresh. |

Both are small on purpose. The package does not know whether you use SQL, a document store or a file, and it does not run a
queue or a cache of its own.

## `ConnectionRepository`

```php
interface ConnectionRepository
{
    public function find(int $id): ?IdpConnection;
}
```

One method. Return `null` for an unknown id. A connection that is switched off (`enabled` is `false`) signs nobody in, but you
still return it, so that an administrator can see it and turn it on again. See [Connections](../connections/).

## `RequestStore`

```php
interface RequestStore
{
    public function remember(string $requestId, int $connectionId, string $bindingHash, ?string $returnTo): void;

    public function consume(string $requestId): ?PendingRequest;
}
```

`consume()` is the part to get right: a request may be used **once** and only while it is **fresh**. See
[Request storage](../request-storage/) for a full example and the pitfalls.

## What stays yours

What to do with the identity is not decided here:

- matching or creating an account
- multi-factor authentication
- starting a session
- which email domains you allow at all
- the routes and the pages people see

See [The identity](../identity/) for how to match people safely.
