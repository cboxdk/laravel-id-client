---
title: Cbox ID client SDK
description: Laravel/PHP consumer SDK for authenticating an app against a Cbox ID instance
weight: 1
---

# Cbox ID client SDK

`cboxdk/laravel-id-client` is the **consumer** side of Cbox ID — the small package
a *product* installs to authenticate its users against a running Cbox ID instance
(the opposite end from `cboxdk/laravel-id`, which *is* the identity platform).

> **Status: scaffold.** This package currently ships only its service-provider
> wiring; the typed client surface below is being built. Until it lands, consume
> the platform with a standard OIDC client as shown in
> [Consuming the platform today](consuming-today.md) — the endpoints are standard
> OAuth2/OIDC, so nothing here blocks you. This page documents the intended surface
> so the shape is clear; it does **not** describe methods that exist yet.

## What it will do

A product should never re-implement OIDC. This SDK wraps the Cbox ID endpoints
behind a few typed calls:

- **Login (OIDC RP):** redirect to Cbox ID, handle the callback, validate the
  `id_token` (issuer, audience, nonce, signature against the published JWKS).
- **Read identity:** the canonical `sub`, profile claims, and org/`org` context
  from the token or UserInfo.
- **Entitlements:** check what the user's org is entitled to (from token claims or
  the decision API) — see the platform's
  [Entitlements & billing](../../laravel-id/docs/entitlements-and-billing.md).
- **Tokens:** refresh with rotation, and revoke on logout.

## Which package do I install?

| You are building… | Install |
|---|---|
| the identity provider itself (the login/SSO/SCIM service) | [`cboxdk/laravel-id`](../../laravel-id/docs/index.md) |
| a product that logs its users in **against** that provider | `cboxdk/laravel-id-client` (this package) |

New to the whole model? Read
[Start here](../../laravel-id/docs/start-here.md) first.

## Sections

- [Installation](getting-started/installation.md)
- [Consuming the platform today](consuming-today.md) — the standard-OIDC path that
  works right now, and what the SDK will shorten.

## License

MIT © Cbox.
