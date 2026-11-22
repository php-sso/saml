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

## Documentation

The documentation is at **<https://php-sso.github.io/saml/>**:

- [How it works](https://php-sso.github.io/saml/how-it-works/): what happens between the click and the person you know
- [Wiring](https://php-sso.github.io/saml/wiring/): the routes, the cookie and a first working sign-in
- [Connections](https://php-sso.github.io/saml/connections/): registering your application at a company's provider
- [Request storage](https://php-sso.github.io/saml/request-storage/): where requests wait, with a store to start from
- [The identity](https://php-sso.github.io/saml/identity/): who signed in, and how to match them safely
- [Errors and troubleshooting](https://php-sso.github.io/saml/errors/): why a sign-in was refused
- [What it decides](https://php-sso.github.io/saml/what-it-decides/): what the library checks, refuses and does not support

The pages are in [`docs/content`](docs/content), built with Hugo and the
[php-sso docs theme](https://github.com/php-sso/docs-theme). Preview them with `hugo server --source docs`.

## Development

```
composer install
composer check     # phpstan, then phpunit
```

The tests build real signed answers with a fake identity provider (`tests/support/IdpFixture.php`) and run `SignIn` against
in-memory implementations of both interfaces.

## License

MIT. See [LICENSE](LICENSE).
