---
title: saml
lead: "SAML 2.0 for PHP applications: people sign in through their company's identity provider."
actions:
  - name: Install it
    url: /installation/
  - name: How it works
    url: /how-it-works/
  - name: Wire it in
    url: /wiring/
---

SAML 2.0 for PHP applications. Today it covers one role, the **service provider**: people sign in to your application
through their company's identity provider (Okta, Entra, ADFS, Keycloak and others). The **identity provider** role (your
application vouching for people at another SSO instance) is not built; it would live next to this one in
`PhpSso\Saml\IdentityProvider`, sharing the SAML plumbing.

- The XML and signature work is done by [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml), never by hand-written
  XML signature code. This package decides what to ask of it and what to trust.
- No framework and no HTTP layer: you pass in the posted `SAMLResponse` and the browser's cookie and get an identity back.
  Your connections and your request storage are interfaces you implement.

## The whole thing in four calls

```php
$signIn = new SignIn($connections, $requests, 'https://sso.example.com');

$signIn->metadataXml($id);                      // 1. hand this to the company's administrator
$started = $signIn->begin($id, '/account');     // 2. send the browser to the company's login
$done = $signIn->finish($id, $response, $cookie); // 3. check the signed answer that comes back
$done->identity;                                // 4. who signed in
```

Everything around those calls (routes, sessions, accounts) stays yours.

## Where to go next

| If you want to | Read |
| --- | --- |
| Add the package to a project | [Install](installation/) |
| Understand what happens between the click and the session | [How it works](how-it-works/) |
| See the two interfaces you implement | [What you provide](what-you-provide/) |
| Build the routes and a first working sign-in | [Wiring](wiring/) |
| Set up a company's provider, and keep its certificate current | [Connections](connections/) |
| Decide who the person is in your application | [The identity](identity/) |
| Find out why a sign-in was refused | [Errors and troubleshooting](errors/) |
| Know what the library trusts and refuses | [What it decides](what-it-decides/) |
