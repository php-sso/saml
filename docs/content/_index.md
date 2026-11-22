---
title: saml
lead: "SAML 2.0 for PHP applications: people sign in through their company's identity provider."
actions:
  - name: Install it
    url: /installation/
  - name: What it decides
    url: /what-it-decides/
---

SAML 2.0 for PHP applications. Today it covers one role, the **service provider**: people sign in to your application
through their company's identity provider (Okta, Entra, ADFS, Keycloak and others). The **identity provider** role (your
application vouching for people at another SSO instance) is not built; it would live next to this one in
`PhpSso\Saml\IdentityProvider`, sharing the SAML plumbing.

- The XML and signature work is done by [`onelogin/php-saml`](https://github.com/SAML-Toolkits/php-saml), never by hand-written
  XML signature code. This package decides what to ask of it and what to trust.
- No framework and no HTTP layer: you pass in the posted `SAMLResponse` and the browser's cookie and get an identity back.
  Your connections and your request storage are interfaces you implement.
