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
| `api_url` | `CBOX_ID_API_URL` | Optional. The origin of Cbox ID's `/v1` API, e.g. `https://api.cboxid.com`. Empty (the default), the SDK's own API calls — app catalog, manifest push, service connections, pipe leases — go to `{issuer}/api/v1` as before; set, to `{api_url}/v1`. Sign-in stays on the issuer. |

## The rest (sensible defaults)

| Key | Default | What it does |
|---|---|---|
| `scopes` | `openid profile email` (`CBOX_ID_SCOPES`) | Scopes requested at login, space- or comma-separated. `openid` is required for an `id_token`. Add `organizations` for a team switcher, `offline_access` for a refresh token, `groups` for roles on the id_token — each must be allowed on your app. |
| `sign_in.mode` | `embedded` (`CBOX_ID_SIGN_IN_MODE`) | `redirect`: the app has no sign-in page, and `CboxId::signInRoutes()` serves `/login`, `/signup` and the launch and invitation entries as redirects to Cbox ID. See [Sign in by redirect](../cookbook/sign-in-by-redirect.md). |
| `session.remember` | `true` (`CBOX_ID_REMEMBER_IDENTITY`) | Remember who signed in (subject, organization, tier, roles, permissions — never tokens) in the session, bound to the local user you log in. |
| `authorization.gate` | `false` (`CBOX_ID_GATE`) | Answer `feature:action` abilities (`@can`, `can()`, `authorize()`) from Cbox ID permissions. Only ever grants. |
| `organizations.picker` | `true` (`CBOX_ID_ORGANIZATION_PICKER`) | Send a browser without an organization to the hosted picker from `cbox-id.org`, instead of a 403. |
| `management.key` | — (`CBOX_ID_MANAGEMENT_KEY`) | An environment API key (`cbid_env_…`) for `CboxIdManagement`. Server-side only. |
| `management.url` | `{issuer}/api/v1` (`CBOX_ID_MANAGEMENT_URL`) | Base URL of the environment management API. |
| `app_catalog.enabled` | `false` (`CBOX_APP_CATALOG_ENABLED`) | Turn on the app switcher's catalog. See [App catalog](../cookbook/app-catalog.md). |
| `app_catalog.mode` | `machine` (`CBOX_APP_CATALOG_MODE`) | `machine`: the app reads each person's catalog with its own `app_catalog:resolve` token, cached and served stale when Cbox ID is down; login is untouched. `grant`: the 0.19.0 flow, where login asks for `account:apps:read` and the callback prepares a per-person grant. Machine mode falls back to `grant` while the client lacks `app_catalog:resolve`. |
| `app_catalog.path` | `/apps/catalog` (`CBOX_APP_CATALOG_PATH`) | Where `CboxId::appCatalogRoutes()` serves the catalog JSON. `authorize_path`, `callback_path` and `signin_path` default to `/apps/authorize`, `/apps/callback` and `/apps/signin`. |
| `app_catalog.signin_route` | none (`CBOX_APP_CATALOG_SIGNIN_ROUTE`) | Name of your own sign-in launch route, used instead of `/apps/signin` in a `401 {signin_url}`. |
| `app_catalog.middleware` | `['web', 'auth']` | Middleware of the catalog, authorize and callback routes. `signin_middleware` (`['web']`) is the sign-in route's. |
| `app_catalog.renew_before` | `300` | Grant mode: renew the grant this many seconds before it expires. |
| `app_catalog.cache_store` | default cache (`CBOX_APP_CATALOG_CACHE_STORE`) | Holds the machine-mode catalog (fresh for `refresh_after`, at least 60 s; the last good answer for 24 h) and, in grant mode, the renewal lock and the newest rotated tokens. Must support locks and be shared by every web server. |
| `backchannel_logout.enabled` | `false` (`CBOX_ID_BACKCHANNEL_LOGOUT`) | Mount the back-channel logout receiver and sign out ended sessions. See [Back-channel logout](../cookbook/back-channel-logout.md). |
| `backchannel_logout.path` | `/cbox-id/backchannel-logout` (`CBOX_ID_BACKCHANNEL_LOGOUT_PATH`) | Where the receiver listens — register `{app_url}{path}` in the console. |
| `backchannel_logout.cache_store` | default cache (`CBOX_ID_BACKCHANNEL_LOGOUT_CACHE`) | Holds the replay cache, session index and revocation list. Must be shared by every web server. |
| `backchannel_logout.max_age` | `300` (`CBOX_ID_BACKCHANNEL_LOGOUT_MAX_AGE`) | Oldest `iat` accepted, in seconds. |
| `backchannel_logout.remember_tokens` | `subject` (`CBOX_ID_BACKCHANNEL_LOGOUT_REMEMBER_TOKENS`) | When to cycle remember-me tokens: `subject`, `always`, `never`. |
| `client_authentication.method` | automatic (`CBOX_ID_CLIENT_AUTH_METHOD`) | How CIBA and the device grant authenticate: `client_secret_basic`, `client_secret_post`, `private_key_jwt` or `none`. Automatic picks `private_key_jwt` when a key is set, `client_secret_basic` when a secret is. See [CIBA and the device grant](../cookbook/ciba-and-device-grant.md). |
| `client_authentication.private_key` / `key_id` / `algorithm` | — / — / `RS256` (`CBOX_ID_PRIVATE_KEY`, `CBOX_ID_KEY_ID`, `CBOX_ID_KEY_ALGORITHM`) | The PEM key (or a path to it), its registered `kid`, and `RS256` or `ES256`, for `private_key_jwt`. |
| `api_keys.cache_ttl` | `60` (`CBOX_ID_API_KEY_CACHE_TTL`) | Seconds a live customer API key is cached — how long a revoked key keeps working. `0` asks every time. |
| `account_path` | `/account` (`CBOX_ID_ACCOUNT_PATH`) | Path of the person's hosted account area that `redirectToProfile()` sends users to (a `return_to` is appended); `apiKeysUrl()` is `{account_path}/api-keys`. |
| `token_cache.enabled` | `true` (`CBOX_ID_TOKEN_CACHE`) | Keep machine tokens, encrypted with the app key, until 60 seconds before they expire. Off means `machineToken()` asks Cbox ID on every call. See [Call Cbox ID APIs](../cookbook/call-cbox-id-apis.md). |
| `token_cache.store` | default cache (`CBOX_ID_TOKEN_CACHE_STORE`) | The cache store for machine tokens. Use one every worker shares. |
| `decisions.fresh_for` | `30` (`CBOX_ID_DECISIONS_FRESH_FOR`) | Seconds a kept permission decision is fresh. After that a page view still gets it and a job asks Cbox ID again; a change asks now. See [Kept permission decisions](../cookbook/kept-permission-decisions.md). |
| `decisions.max_age` | `86400` (`CBOX_ID_DECISIONS_MAX_AGE`) | The oldest kept decision that is used at all. |
| `decisions.store` | default cache (`CBOX_ID_DECISIONS_STORE`) | The cache store for kept decisions. Use one every web server and worker shares. |
| `decisions.queue_connection` / `decisions.queue` | app defaults (`CBOX_ID_DECISIONS_QUEUE_CONNECTION`, `CBOX_ID_DECISIONS_QUEUE`) | Where the `RefreshKeptDecision` job runs. |
| `http_timeout` | `10` (`CBOX_ID_HTTP_TIMEOUT`) | Timeout, in seconds, for back-channel calls. |
| `http_connect_timeout` | `3` (`CBOX_ID_HTTP_CONNECT_TIMEOUT`) | Seconds any call to Cbox ID may spend opening a connection. |
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
