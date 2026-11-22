---
title: Install
weight: 1
group: "Get started"
lead: "One Composer package, PHP 8.2 or newer."
---

```bash
composer require php-sso/saml
```

## Requirements

- PHP 8.2 or newer
- The extensions `ext-dom`, `ext-mbstring` and `ext-openssl`
- [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml) ^4.5, which Composer installs for you

There is no framework requirement and no HTTP layer. The package never reads the request or sends a response on its own, so
it works with plain PHP, PSR-7 and PSR-15 applications, and any framework.

## Check it works

The quickest check is to build the metadata for a connection. It needs no identity provider, only the address your
application is reached at:

```php
use PhpSso\Saml\ServiceProvider\SignIn;

$signIn = new SignIn($connections, $requests, 'https://sso.example.com');

echo $signIn->entityId(1);
// https://sso.example.com/saml/1/metadata
```

If that prints an address, the package is installed and autoloading. The next step is [wiring it in](../wiring/).

> [!NOTE]
> Use the address people really reach your application at, including `https://`. The entity id and the reply address are built
> from it, and so is the choice of how the browser is tied to a request (see [How it works](../how-it-works/#the-browser-cookie)).
