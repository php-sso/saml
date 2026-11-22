# Changelog

All notable changes are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project follows [Semantic Versioning](https://semver.org/).

## 1.1.0 - 2026-11-22

- Signed sign-in requests. `new SignIn(..., $signingKey)` takes an `SpSigningKey` (a private key and its certificate, with an optional
  next certificate to publish ahead of a change of key), and `IdpConnection` has a `signRequests` flag. A signed request uses
  RSA-SHA256 over the query string; the metadata carries the certificate and `AuthnRequestsSigned`. A connection that asks for
  signing without a key is refused.
- `SpSigningKey::generate()` makes a key and a self-signed certificate, and `SpSigningKey::fingerprint()` shows a certificate's fingerprint.
- Not breaking: both new constructor arguments are optional.

## 1.0.0 - 2026-11-21

First release.

- SAML 2.0 service provider: people sign in through their company's identity provider (HTTP-Redirect request, HTTP-POST answer),
  with the XML and signature work done by onelogin/php-saml.
- Answers are accepted only for a request we sent, once, and only from the browser that started it.
- The email address counts as verified only at the domains the connection claims.
- Connections and request storage sit behind interfaces you implement.
