# cboxdk/laravel-id-client

Laravel/PHP **consumer** SDK for Cbox ID — the package a *product* installs to
authenticate its users against a running Cbox ID instance (the opposite end from
[`cboxdk/laravel-id`](../laravel-id), which *is* the identity platform).

Part of **Cbox ID** — the self-hostable, Laravel-native identity platform. MIT licensed.

> **Status: scaffold.** Ships the service-provider wiring; the typed client surface
> is being built. Cbox ID speaks standard OAuth2/OIDC, so you can authenticate
> against it today with a standard OIDC client — see the docs.

## Install

```bash
composer require cboxdk/laravel-id-client
```

Requires PHP `^8.4` and Laravel 12 or 13.

## Documentation

Full docs in [`docs/`](docs/index.md):

- [Overview](docs/index.md) — what this SDK is (and isn't yet), and which package to install.
- [Installation](docs/getting-started/installation.md) — install + what you need from the instance.
- [Consuming the platform today](docs/consuming-today.md) — the standard-OIDC path that works now.

New to central identity? Start with
[`cboxdk/laravel-id` → Start here](../laravel-id/docs/start-here.md).

## License

MIT © Cbox.
