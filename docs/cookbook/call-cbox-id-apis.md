---
title: Call Cbox ID APIs
description: Machine (client-credentials) tokens, the UserInfo endpoint, and RFC 7662 token introspection.
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

Mint tokens per unit of work; they're short-lived by design. Don't cache one for the
life of the process.

## UserInfo (as a user)

Fetch the OIDC claims for a user access token — for example to refresh the profile
after login:

```php
$claims = CboxId::userinfo($cbox->accessToken);
// ['sub' => '...', 'email' => '...', 'name' => '...', ...]
```

`authenticate()` already merges UserInfo into the returned `CboxUser`, so you rarely
need this at login — reach for it later, when you have only a stored access token.

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

## What needs the secret

| Call | Needs client secret |
|---|---|
| `machineToken()` | yes |
| `introspect()` | yes |
| `userinfo()` | no (bearer token only) |

Because the secret is involved, keep these calls server-side — never from a browser
or a public (SPA/mobile) client.
