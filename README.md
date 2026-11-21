# php-sso/saml

SAML 2.0 for PHP applications. Today it covers one role, the **service provider**: people sign in to your application
through their company's identity provider (Okta, Entra, ADFS, Keycloak and others). The **identity provider** role (your
application vouching for people at another SSO instance) is not built; it would live next to this one in
`PhpSso\Saml\IdentityProvider`, sharing the SAML plumbing.

- The XML and signature work is done by [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml), never by hand-written
  XML signature code. This package decides what to ask of it and what to trust.
- No framework and no HTTP layer: you pass in the posted `SAMLResponse` and the browser's cookie and get an identity back.
  Your connections and your request storage are interfaces you implement.

## Install

```
composer require php-sso/saml
```

PHP 8.2 or newer, with `ext-dom`, `ext-mbstring` and `ext-openssl`.

## Service provider

### What it decides

- A sign-in request goes out as an HTTP-Redirect `AuthnRequest`. Exactly one answer can come back, **for a request we sent**,
  **once**, and **only to the browser that started it** (a secret cookie, hashed in your store). Unsolicited answers,
  replays, answers to another connection's request and answers carried by another browser are refused.
- Over plain http (development) browsers do not send the cookie back on a cross-site POST, so there it is checked only when
  present; over https its absence refuses the answer.
- The answer is parsed with a hardened loader (a `DOCTYPE` or entities are refused) before anything in it is read, and
  nothing in it is believed until `onelogin/php-saml` has validated it in strict mode: signature against the connection's
  certificate, destination, recipient, audience, timing and `InResponseTo`.
- The person is identified by the **NameID**, never by email alone. An email address counts as **verified** only when it is at
  a domain the connection claims: a company cannot vouch for an address at someone else's domain.
- Email, display name and first and last name are found among the usual attribute names (`email`, `mail`, the Microsoft
  claim URIs, `urn:oid:` names), and a NameID in email format is used when no attribute has the address.

Not supported yet: signed or encrypted requests, single logout, and HTTP-POST for the request.

### What you provide

| Interface | You provide |
| --- | --- |
| `ConnectionRepository` | The identity providers you have been set up with (`IdpConnection`): entity id, sign-on URL, signing certificate, on or off, and the email domains it may vouch for. |
| `RequestStore` | The requests waiting for an answer: remember one (with a hash of the browser secret), and hand it back once, only while it is fresh. |

What to do with the identity (matching or creating an account, multi-factor, sessions, which domains you allow at all) is
yours, in your controller.

### Wiring

```php
use PhpSso\Saml\ServiceProvider\SignIn;
use PhpSso\Saml\ServiceProvider\SignInException;

$signIn = new SignIn($connectionRepository, $requestStore, 'https://sso.example.com');

// Give the company's administrator these when they register your application:
$signIn->entityId($id);      // https://sso.example.com/saml/{id}/metadata
$signIn->acsUrl($id);        // https://sso.example.com/saml/{id}/acs
$signIn->metadataXml($id);

// GET /login/saml/{id}
$started = $signIn->begin($id, returnTo: '/account');
// redirect the browser to $started->url, and set $started->binding as a cookie for /saml
// (HttpOnly; Secure; SameSite=None over https, because the answer is a cross-site POST)

// POST /saml/{id}/acs
try {
    $done = $signIn->finish($id, $_POST['SAMLResponse'] ?? '', $_COOKIE['sso_saml'] ?? null);
} catch (SignInException $e) {
    // $e->getMessage() is safe to show people; $e->detail() is for the log
}
$done->identity;   // connectionId, nameId, email, emailVerified, name
$done->returnTo;   // what you passed to begin()
```

The routes are yours: only the paths in `entityId()` and `acsUrl()` are fixed by the address you give the provider.

`finish()` temporarily sets `$_SERVER['REQUEST_URI']` to the reply path, because the underlying library reads the address an
answer arrived at from there, and restores it afterwards.

## Development

```
composer install
composer check     # phpstan, then phpunit
```

The tests build real signed answers with a fake identity provider (`tests/support/IdpFixture.php`) and run `SignIn` against
in-memory implementations of both interfaces.

## License

MIT. See [LICENSE](LICENSE).
