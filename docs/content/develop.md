---
title: "Develop"
weight: 10
group: "Contribute"
lead: "Run the checks, and test your own wiring."
---

```bash
composer install
composer check     # phpstan, then phpunit
```

`composer check` runs PHPStan first (strict rules), then PHPUnit. `composer test` and `composer analyse` run them on their own.

## How the tests work

The tests build **real signed answers** with a fake identity provider (`tests/support/IdpFixture.php`) and run `SignIn` against
in-memory implementations of both interfaces (`InMemoryConnections` and `InMemoryRequests`). Nothing is mocked in the part that
matters: the signature, the destination and the timing are really checked.

They cover the cases that are easy to get wrong:

- an answer nobody asked for, and the same answer sent twice
- an answer carried by another browser, and one without the cookie
- an answer signed with another key
- an answer for another connection, or for a switched-off or unknown one
- an address outside the claimed domains
- XML with a `DOCTYPE`

## Testing your own routes

The test helpers (`IdpFixture`, `InMemoryConnections`, `InMemoryRequests`) belong to the repository's tests. They are **not**
part of the installed package, so you cannot autoload them. Two ways to test your own wiring:

- **Copy them.** They are small, MIT licensed, and have no dependencies beyond the package. Put them in your own `tests/`
  folder: the two in-memory classes let a sign-in run with no database, and `IdpFixture` builds a signed answer for a request,
  which you post to your reply route.
- **Use a real provider locally.** A throwaway Keycloak in a container gives you a full round trip, including the browser
  cookie, which a unit test cannot show you.

Test at least what only your code decides: that a first sign-in creates or connects the right account, that an unverified
address never connects to an existing one, and that `returnTo` is checked before you redirect to it.

## Rules for changes

- A new refusal needs a test that proves it.
- The text people see stays vague; the detail stays exact (see [Errors](../errors/)).
- Use `onelogin/php-saml` for anything about XML or signatures. Do not write signature code here.
