---
title: Requirements
description: The PHP, Laravel and library versions this package's composer.json actually enforces.
weight: 3
---

# Requirements

Exactly what `composer.json` requires — nothing invented.

| Requirement | Constraint |
| --- | --- |
| PHP | `^8.4` (develops and CI on 8.4 and 8.5) |
| Laravel (`illuminate/*`) | `^12.0 \|\| ^13.0` — current and previous major |
| `firebase/php-jwt` | `^7.0` — used to verify the `id_token` against the instance's JWKS |

You also need, at runtime:

- A reachable **Cbox ID instance** and its issuer URL (this package discovers every
  endpoint from `{issuer}/.well-known/openid-configuration`).
- An **OAuth client** registered on that instance — a client id, a client secret
  (for confidential/server-side apps, machine tokens and introspection), and your
  app's callback URL registered as a redirect URI.

No other services are required. HTTP calls use Laravel's HTTP client; discovery and
JWKS are cached via Laravel's cache.
