---
title: Installation
description: Composer install, config publish, and what you need from the Cbox ID instance.
weight: 1
---

# Installation

## Install

```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```

The service provider auto-registers via Laravel package discovery — no manual
registration needed. Publishing the config drops `config/cbox-id-client.php`; see
[Configuration](configuration.md). For versions, see [Requirements](../requirements.md).

## What you need from the Cbox ID instance

To authenticate against a Cbox ID deployment, get these from its operator (or
self-register, if the instance enables Dynamic Client Registration):

| Value | What it is |
|---|---|
| **Issuer URL** | the platform's public URL, e.g. `https://id.acme.com` — its discovery document lives at `/.well-known/openid-configuration` |
| **Client ID / secret** | your product's registered OAuth client |
| **Redirect URI** | your callback URL, registered on that client |

That's everything. All protocol endpoints (authorize, token, userinfo, JWKS,
introspection, end-session) are read from the issuer's discovery document, so you
never configure them by hand.

## Next

- [Configuration](configuration.md)
- [Quickstart](../quickstart.md)
