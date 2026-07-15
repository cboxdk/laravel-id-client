---
title: Quickstart
description: A working "Sign in with Cbox ID" in about five minutes — install, configure, two routes.
weight: 2
---

# Quickstart

Add enterprise-grade login to a Laravel app in about five minutes. You need a Cbox
ID instance and an OAuth client registered on it (client id, secret, and your
callback URL).

## 1. Install

```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```

## 2. Configure

```dotenv
CBOX_ID_ISSUER=https://id.acme.com
CBOX_ID_CLIENT_ID=client_...
CBOX_ID_CLIENT_SECRET=secret_...
CBOX_ID_REDIRECT=https://app.acme.com/auth/callback
```

Every other endpoint is discovered from the issuer, so that's all the config.

## 3. Add two routes

```php
use Cbox\Id\Client\Facades\CboxId;
use Illuminate\Http\Request;

// Kick off login.
Route::get('/auth/redirect', fn () => CboxId::redirect());

// Handle the callback.
Route::get('/auth/callback', function (Request $request) {
    $cbox = CboxId::authenticate($request); // verifies state, PKCE and the id_token

    $user = User::updateOrCreate(
        ['cbox_id' => $cbox->id],                 // key on the stable subject
        ['email' => $cbox->email, 'name' => $cbox->name],
    );

    auth()->login($user);

    return redirect()->intended('/dashboard');
})->name('auth.callback');
```

That's it — a full authorization-code + PKCE login with a verified `id_token`.

## 4. (Optional) Let users manage their profile

Send a signed-in user to the instance's hosted account page — password, MFA,
passkeys, sessions — and back:

```php
Route::get('/account', fn () => CboxId::redirectToProfile(returnTo: route('dashboard')));
```

## Next

- [Log users in](cookbook/log-in-users.md) — errors, scopes, and the `CboxUser` object.
- [Hosted profile management](cookbook/hosted-profile-management.md).
- [Call Cbox ID APIs](cookbook/call-cbox-id-apis.md) — machine tokens, UserInfo, introspection.
- [Verify webhooks](cookbook/verify-webhooks.md).
