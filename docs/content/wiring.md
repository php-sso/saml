---
title: "Wiring"
weight: 4
group: "Build it"
lead: "Put it together in your application, from the routes to the first sign-in."
---

This page builds a complete sign-in. It assumes you have implemented the two interfaces
([What you provide](../what-you-provide/)); examples of both follow on [Connections](../connections/) and
[Request storage](../request-storage/).

## Create the service

```php
use PhpSso\Saml\ServiceProvider\SignIn;

$signIn = new SignIn($connectionRepository, $requestStore, 'https://sso.example.com');
```

The third argument is the address people reach your application at. The entity id and the reply address are built from it.
One `SignIn` serves every connection.

## Three routes

The routes are yours: only the paths in `entityId()` and `acsUrl()` are fixed by the address you give the provider. These
three are the usual ones.

### 1. Metadata: `GET /saml/{id}/metadata`

Give the company's administrator what they need to register your application:

```php
$signIn->entityId($id);      // https://sso.example.com/saml/{id}/metadata
$signIn->acsUrl($id);        // https://sso.example.com/saml/{id}/acs
$signIn->metadataXml($id);   // the same, as a SAML metadata document
```

Serve `metadataXml()` as `application/samlmetadata+xml` (or `application/xml`). Most providers can read it from the address
directly, which saves anyone from copying values by hand.

### 2. Start: `GET /login/saml/{id}`

```php
use PhpSso\Saml\ServiceProvider\SignInException;

try {
    $started = $signIn->begin($id, returnTo: '/account');
} catch (SignInException $e) {
    // show $e->getMessage(); write $e->detail() to the log
    return;
}

// The browser needs the secret back when the answer arrives, so give it as a cookie for /saml.
setcookie('sso_saml', $started->binding, [
    'expires' => 0,
    'path' => '/saml',
    'secure' => $signIn->secure(),
    'httponly' => true,
    'samesite' => $signIn->secure() ? 'None' : 'Lax',
]);

header('Location: ' . $started->url);
```

`returnTo` is yours to use and comes back unchanged from `finish()`. Only accept paths that belong to your application
before you redirect to it (an open redirect is a classic mistake here).

> [!WARNING]
> Over https the cookie must be `SameSite=None; Secure`. The answer is a cross-site POST, and with `Lax` or `Strict` the browser
> would not send the cookie, so every sign-in would be refused.

### 3. Answer: `POST /saml/{id}/acs`

```php
try {
    $done = $signIn->finish($id, $_POST['SAMLResponse'] ?? '', $_COOKIE['sso_saml'] ?? null);
} catch (SignInException $e) {
    error_log($e->detail());       // for you
    echo $e->getMessage();         // safe to show people
    return;
}

setcookie('sso_saml', '', ['expires' => 1, 'path' => '/saml']);   // the secret has done its job

$identity = $done->identity;   // connectionId, nameId, email, emailVerified, name
$next = $done->returnTo;       // what you passed to begin()

// Find or create the person, start your session, then:
header('Location: ' . ($next ?? '/'));
```

`finish()` temporarily sets `$_SERVER['REQUEST_URI']` to the reply path, because the underlying library reads the address an
answer arrived at from there, and restores it afterwards. It does this even when the answer is refused.

## With PSR-7

The package has no HTTP layer, so a PSR-7 or PSR-15 application passes the pieces in the same way:

```php
$body = (array) $request->getParsedBody();
$cookies = $request->getCookieParams();

$done = $signIn->finish($id, (string) ($body['SAMLResponse'] ?? ''), $cookies['sso_saml'] ?? null);
```

Build the redirect and the `Set-Cookie` header with your own response objects.

## A first test

1. Register a connection with the test provider of your choice (a local Keycloak is the easiest).
2. Open `/login/saml/1`.
3. Sign in at the provider.
4. You land on your reply route with an identity.

When something is refused, the person sees a short message and `$e->detail()` says why. See
[Errors and troubleshooting](../errors/).
