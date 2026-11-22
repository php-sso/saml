---
title: "Connections"
weight: 5
group: "Build it"
lead: "One company's identity provider, how to register your application there, and how to keep it working."
---

A connection is one company's identity provider, as signing in through it needs to know it. Your `ConnectionRepository` returns
them as `IdpConnection` objects.

## The fields

| Field | What it is |
| --- | --- |
| `id` | Your own number for the connection. It is in the entity id and the reply address, so it must not change. |
| `enabled` | Whether anyone can sign in through it. A switched-off connection is refused, with a message that does not say why. |
| `entityId` | The provider's own identifier (its "issuer"). |
| `ssoUrl` | Where the browser is sent to sign in. Only the HTTP-Redirect binding is used. |
| `certificate` | The provider's signing certificate, PEM. Answers must be signed with it. |
| `domains` | The lower-case email domains the provider may vouch for, such as `['acme.com']`. |

## Register your application at the provider

The company's administrator adds your application to their provider. Give them these three things (or the metadata address,
which holds all three):

| You give | Value | Often called |
| --- | --- | --- |
| Entity id | `$signIn->entityId($id)` | Audience, SP entity id, identifier |
| Reply address | `$signIn->acsUrl($id)` | ACS URL, reply URL, single sign-on URL |
| Metadata | `$signIn->metadataXml($id)` | SP metadata |

The names vary between Okta, Entra, ADFS and Keycloak, so match on the value, not the label.

They give you back three things, which fill in the connection:

| You get | Goes in |
| --- | --- |
| The provider's entity id (issuer) | `entityId` |
| The sign-on address (SSO URL) | `ssoUrl` |
| The signing certificate | `certificate` |

Ask them to send the **email address** as an attribute (`email` or `mail` both work), and the name if they can. See
[The identity](../identity/) for every attribute name that is understood.

## Email domains

`domains` is what makes an email address count as verified. A provider is the authority for the domains you list for it and for
no others. If Acme's provider says a person's address is `ceo@example.org` and `example.org` is not in Acme's list, the address
is returned but `emailVerified` is `false`.

> [!WARNING]
> Never list a domain for a company that does not own it. Whoever controls a connection can claim any address in its
> domains, so you may be letting them sign in as people who are not theirs.

## A repository to start from

```php
use PhpSso\Saml\ServiceProvider\Contract\ConnectionRepository;
use PhpSso\Saml\ServiceProvider\IdpConnection;

final class PdoConnections implements ConnectionRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function find(int $id): ?IdpConnection
    {
        $statement = $this->pdo->prepare(
            'SELECT id, enabled, entity_id, sso_url, certificate, domains FROM saml_connections WHERE id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new IdpConnection(
            (int) $row['id'],
            (bool) $row['enabled'],
            (string) $row['entity_id'],
            (string) $row['sso_url'],
            (string) $row['certificate'],
            array_values(array_filter(array_map('trim', explode(',', strtolower((string) $row['domains']))))),
        );
    }
}
```

```sql
CREATE TABLE saml_connections (
    id          INTEGER PRIMARY KEY,
    enabled     BOOLEAN NOT NULL DEFAULT 1,
    entity_id   TEXT NOT NULL,
    sso_url     TEXT NOT NULL,
    certificate TEXT NOT NULL,
    domains     TEXT NOT NULL      -- comma separated, lower case
);
```

## Keeping a connection working

- **Certificates expire.** A connection holds one certificate. When the company rotates theirs, answers signed with the new one
  are refused ("Signature validation failed") until you update the connection. Ask the company's administrator to tell you
  before they rotate, and swap the certificate at the same moment.
- **Switching off** is the quick way to stop a company's people signing in. It takes effect at once, and also for sign-ins
  that were already started.
- **Do not reuse an id.** The id is in the entity id the company registered. A new connection with an old id would receive
  answers meant for the old one.
