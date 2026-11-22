---
title: "What you provide"
weight: 3
lead: "The interfaces your application implements."
---

| Interface | You provide |
| --- | --- |
| `ConnectionRepository` | The identity providers you have been set up with (`IdpConnection`): entity id, sign-on URL, signing certificate, on or off, and the email domains it may vouch for. |
| `RequestStore` | The requests waiting for an answer: remember one (with a hash of the browser secret), and hand it back once, only while it is fresh. |

What to do with the identity (matching or creating an account, multi-factor, sessions, which domains you allow at all) is
yours, in your controller.
