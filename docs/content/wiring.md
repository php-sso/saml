---
title: "Wiring"
weight: 4
lead: "Put it together in your application."
---

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
