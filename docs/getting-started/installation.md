---
title: Installation
description: Install the Cbox ID consumer SDK in a product app
weight: 1
---

# Installation

## Requirements

- PHP `^8.4`
- Laravel 12 or 13 (`illuminate/*` `^12.0 || ^13.0`)
- A reachable Cbox ID instance (you'll need its issuer URL and a registered client)

## Install

```bash
composer require cboxdk/laravel-id-client
```

The service provider auto-registers via Laravel package discovery — no manual
registration needed.

## What you need from the Cbox ID instance

To authenticate against a Cbox ID deployment, get these from its operator (or
self-register via [Dynamic Client Registration](../../../laravel-id/docs/standards.md)):

| Value | What it is |
|---|---|
| **Issuer URL** | the platform's public URL, e.g. `https://id.acme.com` — its discovery document lives at `/.well-known/openid-configuration` |
| **Client ID / secret** | your product's registered OAuth client |
| **Redirect URI** | your callback URL, registered on that client |

> **Status:** the SDK is currently a scaffold (service-provider wiring only). Until
> the typed client lands, wire login with a standard OIDC client — see
> [Consuming the platform today](consuming-today.md).

## Next

- [Consuming the platform today](consuming-today.md)
- [SDK overview](../index.md)
