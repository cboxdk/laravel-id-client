---
title: Integration prompt (for AI agents)
description: A self-contained, copy-paste brief that hands an AI coding agent (or a developer) everything needed to add Cbox ID login to a Laravel SaaS.
weight: 6
---

# Integration prompt (for AI agents)

A reusable brief for adding Cbox ID login to any Laravel SaaS — ai-assistant, cortex,
or a new product. Paste the fenced block below into Claude Code (or hand it to a
developer). It is self-contained: an agent can execute it without this documentation,
and every method it names is real (see [Log in users](log-in-users.md),
[Hosted profile management](hosted-profile-management.md),
[Call Cbox ID APIs](call-cbox-id-apis.md), [Verify webhooks](verify-webhooks.md)).

> **Not a Laravel app?** Use the same OIDC flow with `@cboxdk/id-js`, the Python
> `cbox-id-client`, or `github.com/cboxdk/id-go` — the method names mirror the facade.

````markdown
# Task: Integrate Cbox ID authentication into this Laravel SaaS

Add "Sign in with Cbox ID" to this application using the official client SDK. The app
is a **relying party**: users authenticate against a running Cbox ID instance via
standard OpenID Connect. Do NOT hand-roll OAuth — the SDK handles PKCE, state, nonce,
and full id_token verification.

## Prerequisites (ask me if not set)
- A reachable Cbox ID instance — its **issuer URL** (e.g. `https://id.acme.com`).
- An **OAuth client** registered on it: `client_id`, `client_secret`, and this app's
  callback URL registered as a redirect URI.

## Steps

### 1. Install + configure
```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```
Add to `.env` (every other endpoint is auto-discovered from the issuer):
```dotenv
CBOX_ID_ISSUER=https://id.acme.com
CBOX_ID_CLIENT_ID=client_...
CBOX_ID_CLIENT_SECRET=secret_...
CBOX_ID_REDIRECT=https://this-app.com/auth/cbox/callback
```

### 2. Login + callback routes (in the `web` middleware group, so the session works)
```php
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Exceptions\{AuthenticationFailed, InvalidState};
use Illuminate\Http\Request;

Route::get('/auth/cbox/redirect', fn () => CboxId::redirect())->name('cbox.redirect');

Route::get('/auth/cbox/callback', function (Request $request) {
    try {
        $cbox = CboxId::authenticate($request); // verifies state, PKCE, id_token
    } catch (InvalidState) {
        return redirect()->route('cbox.redirect');     // forged/stale — restart
    } catch (AuthenticationFailed $e) {
        report($e);
        return redirect('/login')->withErrors('Sign-in could not be completed.');
    }

    // Key the local account on the STABLE subject, never on email.
    $user = User::updateOrCreate(
        ['cbox_id' => $cbox->id],
        ['email' => $cbox->email, 'name' => $cbox->name],
    );

    auth()->login($user, remember: true);
    return redirect()->intended('/dashboard');
})->name('cbox.callback');
```
Add a `cbox_id` (string, unique, nullable) column to `users` via a migration and make
it fillable. Add a "Sign in with Cbox ID" button linking to `route('cbox.redirect')`.

### 3. Hosted profile management (password, MFA, passkeys — hosted by the instance)
```php
Route::get('/account', fn () => CboxId::redirectToProfile(returnTo: route('dashboard')))
    ->middleware('auth');
```

### 4. Logout (end the Cbox ID session too, when the instance supports it)
```php
Route::post('/logout', function () {
    $url = CboxId::logoutUrl(returnTo: url('/'));
    auth()->logout();
    return $url ? redirect($url) : redirect('/');
});
```

## Optional — only if the app needs them
- **Call Cbox ID / your APIs as this app:** `CboxId::machineToken(scopes: ['reports.read'])`.
- **Read fresh user claims:** `CboxId::userinfo($accessToken)`.
- **Verify inbound webhooks:** `CboxId::verifyWebhook(payload: $request->getContent(),
  signatureHeader: $request->header('X-Cbox-Signature'), secret: config('services.cbox_id.webhook_secret'))`
  — use the RAW body.
- The `CboxUser` also exposes `->organizationId`, `->claims`, `->claim('key')`,
  `->accessToken`, `->refreshToken`, `->expiresIn`.

## Hard requirements (do not skip)
- Key local accounts on `$cbox->id` (the `sub`), NOT email — email changes/reassigns.
- Keep `CLIENT_SECRET` server-side only; the callback route MUST be in the `web` group
  (the SDK stores state/verifier/nonce in the session).
- Serve over HTTPS.

## Acceptance criteria
- Clicking the button redirects to the instance, and after login the browser returns
  to `/dashboard` signed in.
- A local `users` row exists keyed on `cbox_id`.
- `/account` redirects to the instance's hosted profile page and back.
- Tampering with the `state` on the callback is rejected (InvalidState path).

## Reference
The `CboxId` facade methods are `redirect`, `authenticate`, `profileUrl`,
`redirectToProfile`, `logoutUrl`, `machineToken`, `userinfo`, `introspect`, `verifyWebhook`.
````
