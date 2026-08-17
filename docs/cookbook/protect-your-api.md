---
title: Protect your API
description: Verify a Cbox ID access token presented to your own API, locally, and gate routes on scopes.
weight: 5
---

# Protect your API

The rest of this cookbook is about tokens minted **for** you at the end of a login.
This recipe is the mirror image: a token presented **to** your API by a caller who
already has one, and the two questions you have to answer before serving them.

1. Is this token real, unexpired, from our issuer, and meant for *this* API?
2. May the caller do *this particular thing*?

## Wire it up

Set the resource identifier this API answers to:

```dotenv
CBOX_ID_ISSUER=https://id.example.com
CBOX_ID_AUDIENCE=https://api.example.com
```

Then declare what each route needs:

```php
Route::middleware(['api', 'cbox-id.token'])->group(function () {
    Route::get('/me', fn () => app(VerifiedToken::class)->subject);
});

Route::middleware(['api', 'cbox-id.token:orders.write'])->post('/orders', …);
```

Scopes are middleware parameters, so a route states its own requirement where
anyone reading the route file can see it — rather than in a check buried in a
controller that the next endpoint forgets to copy.

## Reading the token

Resolve `VerifiedToken` from the container:

```php
use Cbox\Id\Client\ValueObjects\VerifiedToken;

public function store(Request $request, VerifiedToken $token): JsonResponse
{
    $tenant = $token->organizationOrFail();
    …
}
```

There is no path that produces a `VerifiedToken` from an unverified string, so
holding one *is* the proof it was checked. That is the reason it is resolved from
the container rather than read off the request as an attribute — a handler cannot
accidentally work with the raw string.

`organizationOrFail()` exists because a client-credentials token minted for no
organization carries `org: null`, and a multi-tenant API that reads null as "any
organization" has just built a cross-tenant hole. Ask for the tenant here rather
than reaching for the nullable property and forgetting the null.

## What the caller sees

| Situation | Status | `error.type` |
| --- | --- | --- |
| No bearer token presented | 401 | `invalid_request` |
| Signature, issuer, audience or expiry failed | 401 | `invalid_token` |
| Valid token, missing scope | 403 | `insufficient_scope` |

Which of the two a rejection is comes from the token, never from the route. A route
that declares a scope still answers 401 `invalid_token` for a forged or expired one —
sending that caller after a broader grant would be advice that cannot work.

Every rejection carries a `WWW-Authenticate` header per RFC 6750 §3, so a
conformant client acts on the header without parsing prose. **None of them is a
500** — the distinction decides what the caller does next. A 401 saying the token
expired is answered by fetching a new one; a 500 is answered by retrying the same
dead credential until somebody notices.

The 403 is not interchangeable with the 401 either: the caller is who they say
they are and may not do this, so a fresh token of the same kind will not help.

## Verification is local, and that is deliberate

No introspection call. The signature is checked against the issuer's published
keys, which your app already caches for login. The alternative puts another
service on the hot path of every request and makes your API unavailable whenever
that one is. Cbox ID takes the same view from its side — it embeds coarse
entitlements in the token precisely so a resource server can decide alone.

The cost is real and worth stating: **a revoked token stays usable until it
expires.** Answer that with short token lifetimes, not with a network call per
request. If you need immediate revocation on a specific high-value operation,
call introspection for that operation only — see
[Call Cbox ID APIs](call-cbox-id-apis.md).

## Separating environments

An access token carries no environment claim, so don't invent one. Give each
environment its own resource identifier — `https://api.example.com` and
`https://sandbox.example.com` — and set `CBOX_ID_AUDIENCE` accordingly. A sandbox
token then cannot be spent in production, enforced by a signature check that
already runs rather than by a convention in a config file beside it.

## What is refused on purpose

**Sender-constrained tokens.** A token carrying `cnf` (RFC 9449 DPoP) is bound to
the holder's key. This package refuses it rather than accepting it as a bearer:
accepting it would remove the protection while appearing to honour it, since the
holder's key is never checked and a stolen token would work exactly as the
binding was meant to prevent. Request tokens without a DPoP binding, or add proof
validation before accepting them.

**Tokens with no audience.** `aud` is required. The issuer sets it on every access
token — the requested resource, or the issuer itself when none was requested
(RFC 9068 §2.2) — so requiring it never rejects a legitimate token, and it is what
stops two services that trust the same issuer from accepting each other's tokens.
