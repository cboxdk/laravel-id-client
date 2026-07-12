---
title: Consuming the platform today
description: Authenticate against Cbox ID with a standard OIDC client until the typed SDK lands
weight: 2
---

# Consuming the platform today

The SDK is a scaffold, but you are **not blocked**: Cbox ID speaks standard
OAuth2/OIDC, so any conformant client authenticates against it now. This page shows
the standard-OIDC path and what the SDK will eventually shorten.

## The flow

Cbox ID is the **Identity Provider**; your product is an **OIDC client (relying
party)**. The standard authorization-code + PKCE flow:

1. Redirect the user to the platform's `authorization_endpoint` with `state` +
   `nonce` + a PKCE challenge.
2. The user authenticates (password/passkey/MFA) **at Cbox ID**, not in your app.
3. Cbox ID redirects back to your `redirect_uri` with a `code`.
4. Exchange the `code` at the `token_endpoint` for an `id_token` + `access_token`.
5. **Validate the `id_token`:** signature against the platform's JWKS, `iss`, `aud`,
   `exp`, and that `nonce` matches what you sent.
6. The `sub` claim is the user's canonical id — the same across every product that
   trusts this platform.

Discover every endpoint from `{issuer}/.well-known/openid-configuration`; never
hardcode paths.

## With Laravel Socialite (generic OIDC)

Point Socialite's generic OIDC driver at the discovery document:

```php
// config/services.php
'cbox_id' => [
    'client_id'     => env('CBOX_ID_CLIENT_ID'),
    'client_secret' => env('CBOX_ID_CLIENT_SECRET'),
    'redirect'      => env('CBOX_ID_REDIRECT_URI'),
    'issuer'        => env('CBOX_ID_ISSUER'),   // https://id.acme.com
],
```

```php
Route::get('/auth/redirect', fn () => Socialite::driver('cbox_id')->redirect());

Route::get('/auth/callback', function () {
    $user = Socialite::driver('cbox_id')->user();   // validated id_token
    // $user->getId() === the canonical `sub`; map it to your local record.
});
```

`league/oauth2-client` (or any OIDC library) works the same way — the contract is
the discovery document, not a vendor SDK.

## Reading org context & entitlements

- **Org context** rides in the token as the `org` claim.
- **Entitlements** are either embedded as claims (coarse, `EnforcementMode::Claims`)
  or checked live via the decision API (`EnforcementMode::DecisionApi`). See the
  platform docs: [Entitlements & billing](../../laravel-id/docs/entitlements-and-billing.md).

Read these from the validated token; don't call the product's own billing to
re-derive them.

## What the SDK will shorten

Everything above is standard OIDC boilerplate. When the typed client lands it will
collapse the redirect/callback/validation/refresh/revoke steps into a few calls and
provide typed accessors for identity, org, and entitlements — but the wire protocol
won't change, so migrating to it is a refactor, not a re-integration.

## Next

- [SDK overview](index.md)
- Platform side: [Standards](../../laravel-id/docs/standards.md),
  [Integrating an existing app](../../laravel-id/docs/integrating-existing-apps.md).
