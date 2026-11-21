# Changelog

All notable changes are listed here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project follows [Semantic Versioning](https://semver.org/).

## 1.0.0 - 2026-11-21

First release.

- SAML 2.0 service provider: people sign in through their company's identity provider (HTTP-Redirect request, HTTP-POST answer),
  with the XML and signature work done by onelogin/php-saml.
- Answers are accepted only for a request we sent, once, and only from the browser that started it.
- The email address counts as verified only at the domains the connection claims.
- Connections and request storage sit behind interfaces you implement.
