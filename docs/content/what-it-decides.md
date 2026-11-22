---
title: "What it decides"
weight: 9
group: "Operate"
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

## Not supported

| Not supported | What it means for you |
| --- | --- |
| Signed or encrypted requests | The `AuthnRequest` is sent unsigned. Providers that insist on signed requests cannot be used. |
| Encrypted assertions | The provider must send assertions in the clear (signed, over https). |
| Single logout | Signing out of your application does not sign people out of the company's provider, and the reverse. |
| HTTP-POST for the request | The request always goes out as an HTTP-Redirect. |
| Provider-started sign-in | An answer to a request you did not send is refused. Start from your own sign-in page. |
| Several certificates per connection | A connection has one signing certificate. A rotation means a short change window. |
| The identity provider role | Your application cannot yet vouch for people to another SAML service. |
