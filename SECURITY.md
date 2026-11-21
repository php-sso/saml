# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately through
GitHub's "Report a vulnerability" button on the Security tab of this repository
(private vulnerability reporting). Include what you found, how to reproduce it, and which version.

What to expect:

- An acknowledgement within 3 working days.
- A first assessment within 10 working days.
- A fix and a coordinated disclosure date agreed with you. The default is 90 days at most.
- Credit in the release notes, unless you prefer to stay anonymous.

## Supported versions

Only the latest minor release of the latest major version receives security fixes.

## Scope

In scope: the code in `src/` (starting a sign-in, checking the provider's answer, the request and browser binding, and reading
the identity out of the assertion). Signature and XML validation are done by
[onelogin/php-saml](https://github.com/SAML-Toolkits/php-saml): problems in that library should be reported to its
maintainers, and kept current with `composer update`. Out of scope: your storage, accounts, sessions and admin screens, and the
in-memory implementations used by the tests.

## What you are responsible for

The library decides what to ask of the SAML toolkit and what to trust. These parts are yours:

- Serve the application over HTTPS only. Over plain http the browser binding cannot be enforced and the library says so.
- `RequestStore::consume()` must be atomic and single use: a request that can be consumed twice allows a replayed answer.
  Keep requests for a short time (ten minutes is plenty).
- Set the cookie from `StartedSignIn::$binding` as `HttpOnly; Secure; SameSite=None` on the reply path, and pass it back to
  `finish()`.
- Enter each connection's signing certificate and claimed email domains correctly. A domain claimed by the wrong company lets
  that company vouch for those addresses.
- Do not log the `SAMLResponse` or the identity attributes.
