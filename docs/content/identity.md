---
title: "The identity"
weight: 7
group: "Build it"
lead: "Who the provider says signed in, and how to match them to a person in your application."
---

`finish()` returns a `CompletedSignIn`. Its `identity` is a `SamlIdentity`:

| Field | Type | Meaning |
| --- | --- | --- |
| `connectionId` | `int` | The connection the person signed in through. |
| `nameId` | `string` | The provider's permanent id for the person. |
| `email` | `?string` | Their address, lower-cased, when the provider sent one that can be read. |
| `emailVerified` | `bool` | `true` only when the address is at a domain the connection claims. |
| `name` | `?string` | Their display name, or first and last name together. |

`returnTo` on `CompletedSignIn` is what you passed to `begin()`.

## Match on the NameID, not the email

The **NameID** is how the provider identifies a person, and it does not change when their address does. The email can change
(a marriage, a new domain, a typo fixed), and some providers let people edit it.

So the key for a person is the pair of `connectionId` and `nameId`:

```php
$identity = $done->identity;

$account = $accounts->findByIdentity($identity->connectionId, $identity->nameId);

if ($account === null) {
    // First sign-in through this connection: create or connect an account (see below).
}
```

## First sign-in: create or connect

A first-time person has no link yet. You decide what that means for your application. The safe choices:

- **Create a new account.** No password, no administrator rights, no groups. The identity is the credential.
- **Connect to an existing account** with the same address, but **only when `emailVerified` is `true`**, and ideally only after
  someone has confirmed it.

```php
if ($account === null) {
    $existing = $identity->emailVerified && $identity->email !== null
        ? $accounts->findByEmail($identity->email)
        : null;

    $account = $existing ?? $accounts->create(email: $identity->email, name: $identity->name);
    $accounts->link($account, $identity->connectionId, $identity->nameId);
}
```

> [!WARNING]
> Never connect to an account by an address that is not verified. A company can put any address in an answer, including one at a
> domain it does not own. Without the domain check, that is an account takeover.

## What "verified" means

`emailVerified` is true when the part after the last `@` is in the connection's `domains`. The provider is the authority for
those domains and for no others. It is not a check that the person reads that mailbox, only that this company is allowed to
vouch for addresses there.

## Where the details come from

The package looks for each detail among the names providers usually use. Names are compared without case, and the first
attribute that has a value wins.

| Detail | Attribute names |
| --- | --- |
| Email | `email`, `mail`, `emailaddress`, `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress`, `urn:oid:0.9.2342.19200300.100.1.3` |
| Name | `displayname`, `name`, `cn`, `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name`, `urn:oid:2.16.840.1.113730.3.1.241` |
| First name | `givenname`, `firstname`, `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname`, `urn:oid:2.5.4.42` |
| Last name | `surname`, `sn`, `lastname`, `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname`, `urn:oid:2.5.4.4` |

- If no attribute holds an address and the NameID is in **email format**, the NameID is used as the address.
- An address that is not a valid email is left out, so `email` is `null`, not a broken string.
- If there is no display name, first and last name are joined. With neither, `name` is `null`.

Microsoft's claim URIs and the `urn:oid:` names (used by many university providers) are covered, so most providers work without
any mapping on either side.

## What is not in the identity

Groups, roles and other attributes are not returned. The identity is deliberately small: who they are, and how to reach them.
Decide what a person may do inside your own application, from your own data.
