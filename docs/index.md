---
title: Overview
description: The Laravel client SDK for Cbox ID — turnkey OIDC login, a hosted profile-management redirect, machine tokens, and webhook verification.
weight: 1
---

# cboxdk/laravel-id-client

The **consumer** side of Cbox ID — the small package a *product* installs to
authenticate its users against a running [Cbox ID](https://github.com/cboxdk/laravel-id)
instance. It is the opposite end from `cboxdk/laravel-id`, which *is* the identity
platform.

## Mental model

Cbox ID speaks standard **OpenID Connect**, so under the hood this SDK is a
correct, hardened OIDC relying party — you are never locked into a proprietary
protocol. What the package adds is the ergonomics a hosted-identity product needs,
so you don't hand-roll any of it:

- **Login** — one redirect, one callback. PKCE, CSRF `state`, a nonce, and full
  `id_token` verification (signature against the instance's JWKS, plus issuer,
  audience and nonce) are handled for you.
- **Hosted profile management** — send a signed-in user to the instance's own
  account page (password, MFA, passkeys, sessions) and back to your app.
- **Back-channel calls** — machine (client-credentials) tokens, UserInfo, RFC 7662
  introspection.
- **Webhook / action verification** — confirm an inbound `X-Cbox-Signature`.

Every endpoint is discovered from the issuer's `/.well-known/openid-configuration`,
so the issuer URL is usually the only thing you configure.

## Which package do I install?

| You are building… | Install |
|---|---|
| the identity provider itself (the login / SSO / SCIM service) | [`cboxdk/laravel-id`](https://github.com/cboxdk/laravel-id) |
| a product that logs its users in **against** that provider | `cboxdk/laravel-id-client` (this package) |

## Scope — what this package is, and isn't

This is a **client**. It authenticates users and calls a Cbox ID instance's standard
endpoints. It does **not** configure SSO connections, run SCIM, manage
organizations, or issue tokens — those live on the platform
(`cboxdk/laravel-id`). Keep that boundary in mind when reading the recipes.

## Sections

- **[Quickstart](quickstart.md)** — a working login in five minutes.
- **[Requirements](requirements.md)** — PHP, Laravel and package versions.
- **[Getting started](getting-started/_index.md)** — install and configure.
- **[Cookbook](cookbook/_index.md)** — log in, hosted profile, API calls, webhooks.
- **[Core concepts](core-concepts/_index.md)** — how the login flow works.
- **[Security](security/_index.md)** — what the SDK verifies, and the honest limits.

## License

MIT © Cbox.
