---
title: How login works
description: The authorization-code + PKCE flow the SDK runs, what it stores in the session, and every verification on the callback.
weight: 1
---

# How login works

The SDK is a standard OpenID Connect **relying party** running the
authorization-code flow with PKCE. Nothing proprietary — which is why it's a
redirect and a callback, not a rewrite.

## The flow

```
  Browser            Your app (SDK)                 Cbox ID instance
    │  GET /auth/redirect  │                                 │
    │─────────────────────>│  CboxId::redirect()             │
    │                      │  • make PKCE verifier + S256    │
    │                      │    challenge                    │
    │                      │  • make state + nonce           │
    │                      │  • store all three in session   │
    │   302 to authorize ──┼────────────────────────────────>│
    │                      │                                 │  user authenticates
    │   302 back to callback (?code&state)                   │
    │─────────────────────>│  CboxId::authenticate($request) │
    │                      │  • state == session state?      │
    │                      │  • POST code + verifier ───────>│  token endpoint
    │                      │  •            tokens <──────────│
    │                      │  • verify id_token vs JWKS      │
    │                      │  • merge userinfo               │
    │   your app logs in <─│  returns CboxUser               │
```

## What's stored in the session

`redirect()` puts three short-lived values in the session, all consumed (pulled)
once by `authenticate()`:

| Session key | Purpose |
|---|---|
| `cbox-id-client.state` | CSRF — ties the callback to the browser that started the flow. |
| `cbox-id-client.verifier` | The PKCE verifier — proves the same client that started the flow is redeeming the code. |
| `cbox-id-client.nonce` | Binds the `id_token` to this login, defeating token replay. |

Because they live in the session, a login must finish in the same browser session it
began in.

## What `authenticate()` verifies

Every one of these must pass, or it throws:

1. **State** matches the session value (`hash_equals`) — else `InvalidState`.
2. **No `error`** parameter came back from the provider.
3. **An authorization code** is present, and the PKCE verifier is in the session.
4. **The code exchanges** successfully at the token endpoint (sending the verifier).
5. **An access token** was returned.
6. **The `id_token` verifies** — signature against the instance's JWKS
   (`JWT::decode` enforces signature + expiry), then `iss` equals the configured
   issuer, `aud` contains the client id, and `nonce` matches the session nonce.
7. **A subject (`sub`)** is present on the verified claims.

UserInfo is then merged in to fill claims (email/name/org) a minimal `id_token` may
omit, and you get a `CboxUser`.

## Endpoint discovery

Endpoints aren't configured — they're read from
`{issuer}/.well-known/openid-configuration` and the JWKS from its `jwks_uri`. Both
are cached for `cache_ttl` seconds (default one hour). Rotating a signing key on the
instance therefore becomes usable no later than the cache TTL.

## What this means for you

- Login **must complete in the same session** it started in (the verifier/state/nonce
  live there).
- Sessions must be **available on the callback route** — don't exclude it from the
  `web` middleware group.
- Key local accounts on **`CboxUser::$id`** (the `sub`), which is stable, not on
  email.
