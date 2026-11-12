---
title: Call Cbox ID APIs
description: Machine (client-credentials) tokens, the UserInfo endpoint, RFC 7662 token introspection and RFC 7009 revocation.
weight: 3
---

# Call Cbox ID APIs

Two ways to call a Cbox ID instance's back-channel endpoints: **as a user** (with the
access token from login) or **as your app** (a machine token). Both go through the
`CboxId` facade.

## Machine tokens (as your app)

A client-credentials token authenticates your *app*, not a user — for scheduled
jobs, service-to-service calls, and anything with no user in the loop. Requires the
client secret.

```php
use Cbox\Id\Client\Facades\CboxId;

$token = CboxId::machineToken(scopes: ['reports.read']);

$response = Http::withToken($token)->get('https://api.acme.com/reports');
```

Target a specific resource server (RFC 8707) when the instance uses audience
restriction:

```php
$token = CboxId::machineToken(scopes: ['reports.read'], resource: 'https://api.acme.com');
```

### Token reuse

`machineToken()` keeps each token in your cache and returns it again until 60
seconds before it expires, so you can call it on every request. Cbox ID throttles
token requests per source address, and apps that share an address (a cluster behind
one tunnel or NAT) share that budget. Asking for a new token per request starves them.

- The token is stored encrypted with your app key, one entry per client, scope set
  and resource. Scope order does not matter.
- How long it is kept: its lifetime (the token's `exp`, else the response's
  `expires_in`) minus 60 seconds, or half its lifetime when it lives under two
  minutes; 240 seconds when neither says. Never longer than an hour.
- When the cache store supports locks, one worker fetches while the others wait for
  its token (up to five seconds).
- An entry that cannot be decrypted is dropped and fetched again.

- A token in use is renewed before it expires, so a request finds a valid one. The
  scheduled `cbox-id:refresh` (the package registers it, every five minutes) renews
  each token with less than ten minutes left (`CBOX_ID_TOKEN_REFRESH_AHEAD`). A request
  that finds a token in its last two minutes returns it and renews it after the
  response. A request asks Cbox ID itself only when there is no token at all. A token
  nothing has asked for in seven days (`CBOX_ID_TOKEN_KEEP_WARM_FOR`) is no longer
  renewed.

Pick a store every worker shares with `CBOX_ID_TOKEN_CACHE_STORE`; it defaults to your
default cache store. Turn the cache off with `CBOX_ID_TOKEN_CACHE=false`. It is also off
when the app has no `APP_KEY`, and when you build `IdentityClient` yourself without
passing a `MachineTokenCache`.

The package's own calls already do this: a 401 from the decisions endpoint, a pipe
lease or a service connection drops the kept token. When your own call to an API is
refused (a 401), drop it and ask again:

```php
CboxId::forgetMachineToken(scopes: ['reports.read']);
$token = CboxId::machineToken(scopes: ['reports.read']);
```

### Timeouts

Every call the package makes to Cbox ID carries a connect timeout of three seconds
(`CBOX_ID_HTTP_CONNECT_TIMEOUT`) next to the overall `CBOX_ID_HTTP_TIMEOUT`. An
unreachable Cbox ID fails a call in seconds rather than holding a worker for the whole
timeout.

## UserInfo (as a user)

Fetch the OIDC claims for a user access token — for example to refresh the profile
after login:

```php
$claims = CboxId::userinfo($cbox->accessToken);
// ['sub' => '...', 'email' => '...', 'name' => '...', ...]
```

`authenticate()` already returns those claims in the `CboxUser` (from the id_token, or
from UserInfo when the id_token leaves them out), so you rarely need this at login — reach for it later, when you have only a stored access token.

## Introspection (RFC 7662)

Ask the instance whether a token is currently valid — useful when you receive a
token from elsewhere and must check it live rather than trust a cached claim.
Confidential-client auth (client id + secret) is required.

```php
$result = CboxId::introspect($someToken);

if ($result['active'] === true) {
    // token is live; $result carries sub, scope, exp, ...
}
```

## Revocation (RFC 7009)

Invalidate a token you hold. Revoking a **refresh token** also drops the whole token
family, so this is what a real "sign out everywhere" does. Confidential-client auth is
required.

```php
CboxId::revoke($user->refreshToken, 'refresh_token');
```

The instance answers `200` for an unknown or already-revoked token, so a call that
does not throw means "the token is not valid any more", not "it existed". The
`$tokenTypeHint` (`access_token` / `refresh_token`) only tells the instance which
store to search first.

## What needs the secret

| Call | Needs client secret |
|---|---|
| `machineToken()` | yes |
| `introspect()` | yes |
| `revoke()` | yes |
| `userinfo()` | no (bearer token only) |

Because the secret is involved, keep these calls server-side — never from a browser
or a public (SPA/mobile) client.
