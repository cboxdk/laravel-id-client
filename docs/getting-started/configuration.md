---
title: Configuration
description: Every key in config/cbox-id-client.php and its environment variable.
weight: 2
---

# Configuration

All configuration lives in `config/cbox-id-client.php` (published in
[Installation](installation.md)). In most apps you set the four connection values
in `.env` and leave the rest at their defaults.

## The four you must set

```dotenv
CBOX_ID_ISSUER=https://id.acme.com
CBOX_ID_CLIENT_ID=client_...
CBOX_ID_CLIENT_SECRET=secret_...
CBOX_ID_REDIRECT=https://app.acme.com/auth/callback
```

| Key | Env | What it is |
|---|---|---|
| `issuer` | `CBOX_ID_ISSUER` | Base URL of the Cbox ID instance. Every endpoint is discovered from `{issuer}/.well-known/openid-configuration`. |
| `client_id` | `CBOX_ID_CLIENT_ID` | Your registered OAuth client. |
| `client_secret` | `CBOX_ID_CLIENT_SECRET` | Required for confidential (server-side) apps, machine tokens, and introspection. |
| `redirect` | `CBOX_ID_REDIRECT` | Your callback URL — must exactly match one registered on the client. |

## The rest (sensible defaults)

| Key | Default | What it does |
|---|---|---|
| `scopes` | `['openid', 'profile', 'email']` | Scopes requested at login. `openid` is required for an `id_token`. |
| `account_path` | `'/settings'` | Path of the instance's hosted account page that `redirectToProfile()` sends users to. A `return_to` is appended. |
| `http_timeout` | `10` (`CBOX_ID_HTTP_TIMEOUT`) | Timeout, in seconds, for back-channel calls. |
| `cache_ttl` | `3600` (`CBOX_ID_CACHE_TTL`) | How long, in seconds, the discovery document and JWKS are cached. |

## Notes

- **Keep the secret out of version control** — it lives in `.env`, never in
  `config/` committed to git.
- **`redirect` must match exactly.** A trailing slash or scheme mismatch against the
  value registered on the client is rejected by the authorization server.
- **`account_path` is the instance's account page, not yours.** Change it only if
  your Cbox ID deployment serves its hosted profile at a non-default path.
