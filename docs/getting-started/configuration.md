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
CBOX_ID_ISSUER=https://acme.cboxid.com
CBOX_ID_CLIENT_ID=cid_...
CBOX_ID_CLIENT_SECRET=csec_...
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
| `scopes` | `openid profile email` (`CBOX_ID_SCOPES`) | Scopes requested at login, space- or comma-separated. `openid` is required for an `id_token`. Add `organizations` for a team switcher, `offline_access` for a refresh token, `groups` for roles on the id_token — each must be allowed on your app. |
| `session.remember` | `true` (`CBOX_ID_REMEMBER_IDENTITY`) | Remember who signed in (subject, organization, tier, roles, permissions — never tokens) in the session, bound to the local user you log in. |
| `authorization.gate` | `false` (`CBOX_ID_GATE`) | Answer `feature:action` abilities (`@can`, `can()`, `authorize()`) from Cbox ID permissions. Only ever grants. |
| `organizations.picker` | `true` (`CBOX_ID_ORGANIZATION_PICKER`) | Send a browser without an organization to the hosted picker from `cbox-id.org`, instead of a 403. |
| `management.key` | — (`CBOX_ID_MANAGEMENT_KEY`) | An environment API key (`cbid_env_…`) for `CboxIdManagement`. Server-side only. |
| `management.url` | `{issuer}/api/v1` (`CBOX_ID_MANAGEMENT_URL`) | Base URL of the environment management API. |
| `backchannel_logout.enabled` | `false` (`CBOX_ID_BACKCHANNEL_LOGOUT`) | Mount the back-channel logout receiver and sign out ended sessions. See [Back-channel logout](../cookbook/back-channel-logout.md). |
| `backchannel_logout.path` | `/cbox-id/backchannel-logout` (`CBOX_ID_BACKCHANNEL_LOGOUT_PATH`) | Where the receiver listens — register `{app_url}{path}` in the console. |
| `backchannel_logout.cache_store` | default cache (`CBOX_ID_BACKCHANNEL_LOGOUT_CACHE`) | Holds the replay cache, session index and revocation list. Must be shared by every web server. |
| `backchannel_logout.max_age` | `300` (`CBOX_ID_BACKCHANNEL_LOGOUT_MAX_AGE`) | Oldest `iat` accepted, in seconds. |
| `backchannel_logout.remember_tokens` | `subject` (`CBOX_ID_BACKCHANNEL_LOGOUT_REMEMBER_TOKENS`) | When to cycle remember-me tokens: `subject`, `always`, `never`. |
| `api_keys.cache_ttl` | `60` (`CBOX_ID_API_KEY_CACHE_TTL`) | Seconds a live customer API key is cached — how long a revoked key keeps working. `0` asks every time. |
| `account_path` | `/account` (`CBOX_ID_ACCOUNT_PATH`) | Path of the person's hosted account area that `redirectToProfile()` sends users to (a `return_to` is appended); `apiKeysUrl()` is `{account_path}/api-keys`. |
| `http_timeout` | `10` (`CBOX_ID_HTTP_TIMEOUT`) | Timeout, in seconds, for back-channel calls. |
| `cache_ttl` | `3600` (`CBOX_ID_CACHE_TTL`) | How long, in seconds, the discovery document and JWKS are cached. |

## Notes

- **Keep the secret out of version control** — it lives in `.env`, never in
  `config/` committed to git.
- **`redirect` must match exactly.** A trailing slash or scheme mismatch against the
  value registered on the client is rejected by the authorization server.
- **An unconfigured deployment answers 503, not 500.** Any call that needs an empty
  setting throws `NotConfigured`, naming the key and its environment variable; put
  `cbox-id.configured` (or `cbox-id.configured:issuer,client_id,redirect`) in front of
  routes that need Cbox ID to refuse legibly before they run.
- **`account_path` is the instance's account page, not yours.** Change it only if
  your Cbox ID deployment serves its hosted profile at a non-default path.
