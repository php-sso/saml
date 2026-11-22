---
title: "Errors and troubleshooting"
weight: 8
group: "Operate"
lead: "Why a sign-in was refused, and what to change."
---

Every refusal is a `SignInException`. It has two texts, for two readers:

- `getMessage()` is **safe to show people**. It says what to do next, never why it was refused.
- `detail()` is **for your log**. It says exactly what went wrong.

```php
try {
    $done = $signIn->finish($id, $_POST['SAMLResponse'] ?? '', $_COOKIE['sso_saml'] ?? null);
} catch (SignInException $e) {
    $logger->warning('SAML sign-in refused', ['connection' => $id, 'detail' => $e->detail()]);
    // show $e->getMessage()
}
```

Always log `detail()`. The text people see is the same for many different causes, on purpose: an attacker learns nothing from it.

## What people see, and what it means

| Message | Usually means |
| --- | --- |
| Signing in through your organisation is not available. | The connection does not exist, or is switched off. |
| Signing in through your organisation is not set up correctly. Ask an administrator. | The request could not be built: the connection's address or certificate is wrong. |
| We could not read the answer from your organisation. Please try again. | The answer is not base64, not XML, or contains a `DOCTYPE`. |
| That sign-in attempt expired or was not started here. Please try again. | The answer matches no waiting request: it is old, repeated, unsolicited, or for another connection. |
| That sign-in attempt was not started in this browser. Please try again. | The cookie is missing or does not match. |
| We could not sign you in through your organisation. | The provider's answer failed validation. Read `detail()`. |

## Reading `detail()`

### Answers that match no request

> The SAML response answers no request of ours (unsolicited, replayed, expired or for another connection).

- The person waited more than ten minutes, or pressed back and sent the same answer twice. Both are normal; they start again.
- **Every sign-in fails like this?** The provider may be starting the sign-in itself ("IdP-initiated") instead of answering a
  request. That is not supported.
- Your `RequestStore` may not keep or return requests: check `remember()` and that `consume()` finds the row.
- With several servers, the store is not shared between them.

### The browser cookie

> The SAML response came from a browser that did not start the request.

- Over https the cookie must be `SameSite=None; Secure`. Anything else is not sent with the cross-site POST.
- The cookie path must cover the reply address: `/saml` for `/saml/{id}/acs`.
- A proxy that terminates TLS can make your application think it is on http. Make sure the address you give `SignIn` starts
  with `https://` and that the cookie is set as secure.
- A browser that blocks third party cookies can refuse the cookie. Test in a clean browser profile.

### Answers that failed validation

`detail()` starts with `SAML response refused:` followed by the reason from the underlying library. The usual ones:

| Reason (starts with) | Cause and fix |
| --- | --- |
| `Signature validation failed` | The answer was not signed with the connection's certificate. The company may have rotated it: [update the certificate](../connections/#keeping-a-connection-working). |
| `No Signature found` | Neither the answer nor the assertion is signed. Ask the administrator to turn signing on. |
| `Invalid issuer in the Assertion/Response` | The provider's entity id differs from the connection's `entityId`. Copy it exactly, including a trailing slash. |
| `Invalid audience for this Response` | The provider was given a different entity id than ours. It must be the value of `entityId($id)`. |
| `The response was received at ... instead of ...` | The provider sends to another reply address than `acsUrl($id)`, or a proxy rewrites the path. |
| `Could not validate timestamp: not yet valid` or `expired` | The clocks differ. Fix time on your server (use NTP); the provider's clock is rarely the problem. |
| `The InResponseTo of the Response ... does not match` | The answer is for a different request than the one stored. A double click or an old tab is the usual cause. |
| `A valid SubjectConfirmation was not found` | The answer is for another reply address, or its confirmation has expired. |

### No NameID

> The assertion has no NameID.

The provider sent an answer without saying who signed in. Ask the administrator to send a NameID (any format works).

## When the person is in, but wrong

These are not errors, so they are not in `detail()`:

- **`email` is `null`.** The provider sends no attribute with an address and the NameID is not an email. Ask for `email` or
  `mail` to be sent, or see [the list of names](../identity/#where-the-details-come-from).
- **`emailVerified` is `false`.** The address is at a domain the connection does not claim. Add the domain to the connection
  only if the company really owns it.
- **`name` is `null`.** No display name, and no first and last name, were sent.

## A checklist when nothing works

1. Open `/saml/{id}/metadata` in a browser. Is the entity id what the provider has?
2. Read `detail()` from the first refusal in your log, not the last.
3. Compare the provider's entity id, sign-on address and certificate with the connection, character by character.
4. Check the server's clock.
5. Check the cookie in the browser's developer tools: it should be set on the redirect and sent with the POST.
6. Try on http in development: if it works there but not on https, it is the cookie.
