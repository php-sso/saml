---
title: "What it decides"
weight: 2
lead: "What the library checks, and what it refuses."
---

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
