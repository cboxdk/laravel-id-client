---
title: Security
description: What the SDK verifies on your behalf, what stays your responsibility, and the honest limits of its scope.
weight: 7
---

# Security

This SDK is an OpenID Connect relying party. Its security job is to run the login
flow correctly and to verify everything a client must verify — and to be honest
about where its responsibility ends and yours (or the platform's) begins.

## What the SDK verifies for you

- **PKCE (S256)** on every login — the authorization code can't be redeemed by an
  interceptor that lacks the verifier.
- **CSRF `state`** — the callback must match the browser that began the flow;
  compared with `hash_equals`.
- **Nonce** — the `id_token` must carry the nonce minted for this login, defeating
  replay of a captured token.
- **`id_token` signature** against the instance's JWKS via `firebase/php-jwt`
  (`JWT::decode` enforces signature and expiry) — no hand-rolled JWT parsing.
- **Issuer and audience** — `iss` must equal the configured issuer and `aud` must
  contain your client id, closing token-substitution.
- **Webhook signatures** — HMAC-SHA256 over the raw body with a freshness window and
  a constant-time compare (see [Verify webhooks](../cookbook/verify-webhooks.md)).

See [how login works](../core-concepts/how-login-works.md) for the exact order.

## Your responsibilities

- **Keep `client_secret` and any webhook secret server-side.** Never ship them to a
  browser or a public (SPA/mobile) client. `machineToken()` and `introspect()` use
  the client secret and must run server-side.
- **Serve login over HTTPS** and keep the callback route inside the `web` middleware
  group so the session (which holds the verifier/state/nonce) is present.
- **Key accounts on the subject (`sub`)**, not on email — email can change or be
  reassigned.
- **Keep server time in sync** (NTP), or webhook freshness checks will reject genuine
  calls as stale.
- **Verify webhooks against the raw body**, not a re-encoded copy.

## Honest scope

This package is a **client**. Its threat model covers the login exchange, token
verification, and inbound-signature checks — nothing more. It does **not** provide,
and makes no claim over:

- SSO connection setup, SCIM provisioning, directory sync, or organization
  management — those are platform capabilities of `cboxdk/laravel-id`.
- Password, MFA, passkey, or session policy — enforced by the Cbox ID instance; this
  SDK only redirects the user to the instance's hosted account page.
- Risk scoring / adaptive authentication — an app-and-platform concern, not
  something a client library performs.

The strength of your login is ultimately the strength of the **Cbox ID instance** you
point at: its key management, session policy, and MFA posture. This SDK's guarantee
is narrower and specific — that it talks to that instance correctly and verifies what
it's given.

## Reporting a vulnerability

Report suspected vulnerabilities privately via this repository's **GitHub Private
Vulnerability Reporting** (the *Security* tab → *Report a vulnerability*). Please
don't open a public issue for a security report.
