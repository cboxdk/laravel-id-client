---
title: Core concepts
description: The one idea worth understanding before you rely on the SDK — how the login flow works.
weight: 6
---

# Core concepts

The SDK is deliberately small, so there is one concept worth reading before you rely
on it:

- **[How login works](how-login-works.md)** — the authorization-code + PKCE flow,
  what's stored in the session, and every check performed on the way back.

- **[What a request waits for](local-first.md)** — which calls to Cbox ID happen in a
  request, which in the background, and what the app runs for that.

- [Resource authorization](resource-authorization.md): dedicated API tokens for an already signed-in person.
