---
title: "How it works"
weight: 2
group: "Get started"
lead: "What happens between the click on \"Sign in with your company\" and a person you know."
---

## The two sides

SAML has two roles. The **identity provider** (IdP) is the company's own login: Okta, Entra, ADFS, Keycloak. It knows who
people are. The **service provider** (SP) is your application. It asks the provider who someone is, and trusts only a signed
answer. This package is the service provider.

Each company that signs in this way is a **connection**: one provider, with its own entity id, sign-in address and
certificate. Your application can have as many connections as it has customers, and every connection has its own id.

## One sign-in, step by step

1. **A person chooses to sign in through a company.** Your route calls `begin($connectionId, $returnTo)`.
2. **The package builds a request** (a SAML `AuthnRequest`) and gives it a fresh id. It stores that id, the connection and a
   hash of a random secret with your `RequestStore`.
3. **You redirect the browser** to the address `begin()` returns, and give that browser the secret as a cookie.
4. **The person signs in at the company.** Your application sees none of this: no password, no second step.
5. **The provider posts a signed answer** to your reply address (`/saml/{id}/acs`). The browser carries it, so it arrives as a
   cross-site POST.
6. **Your route calls `finish()`** with the posted `SAMLResponse` and the cookie. The package checks it (see below).
7. **You get an identity**: the provider's id for the person, and their email and name when it sent them. What happens next
   (an account, a session, a second step) is yours.

```
Browser            Your application                  Company's provider
   |  GET /login/saml/7     |                                  |
   |----------------------->|  begin()                         |
   |  302 + cookie          |                                  |
   |<-----------------------|                                  |
   |  GET sign-in address   |                                  |
   |------------------------------------------------------->   |
   |        (the person signs in at the company)               |
   |  POST /saml/7/acs  (signed answer)                        |
   |<-------------------------------------------------------   |
   |----------------------->|  finish()  ->  identity          |
```

## The browser cookie

The answer comes back as a POST from another site, and browsers do not send ordinary session cookies on those. So the package
cannot rely on your session to know the answer belongs to the same person who started. Instead:

- `begin()` returns a random **binding secret**. You set it as a cookie.
- Only its SHA-256 hash is kept in your `RequestStore`.
- `finish()` takes the cookie back and compares. An answer carried by a different browser is refused.

Over `https` the cookie must be `SameSite=None; Secure`, because it has to travel with a cross-site POST. Over plain `http`
(development) browsers refuse that, so there the cookie is checked only when present. This is the one place where the
address you give the package matters for security: it decides which of the two rules applies.

## What `finish()` checks

Before anything in the answer is believed:

- It must be base64 and **safe XML**: a `DOCTYPE` or entities are refused before anything is read.
- It must answer **a request you sent**: its `InResponseTo` is looked up and consumed, so it works **once**, only while the
  request is fresh, and only for the same connection.
- It must come from **the browser that started** it.
- `onelogin/php-saml` must validate it in strict mode: signature against the connection's certificate, destination,
  recipient, audience, timing and `InResponseTo`.
- It must contain a **NameID**.

Only then does the package read the person's details. The full list, with what is not supported, is on
[What it decides](../what-it-decides/).
