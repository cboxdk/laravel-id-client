---
title: Log in users
description: The redirect/callback pair, the CboxUser object, custom scopes, and handling failures.
weight: 1
---

# Log in users

Login is two routes: one starts the flow, one finishes it. The SDK handles PKCE, the
CSRF `state`, the nonce, and full `id_token` verification — see
[how login works](../core-concepts/how-login-works.md) for what happens between them.

## The two routes

```php
use Cbox\Id\Client\Facades\CboxId;
use Illuminate\Http\Request;

Route::get('/auth/redirect', fn () => CboxId::redirect());

Route::get('/auth/callback', function (Request $request) {
    $cbox = CboxId::authenticate($request);

    $user = User::updateOrCreate(
        ['cbox_id' => $cbox->id],
        ['email' => $cbox->email, 'name' => $cbox->name],
    );

    auth()->login($user);

    return redirect()->intended('/dashboard');
})->name('auth.callback');
```

## The `CboxUser` you get back

`authenticate()` returns a `Cbox\Id\Client\ValueObjects\CboxUser`:

| Property | Type | Notes |
|---|---|---|
| `id` | `string` | The stable opaque subject (`sub`). **Key your local account on this**, not on email. |
| `email` | `?string` | Present when the `email` scope was granted. |
| `name` | `?string` | Present when the `profile` scope was granted. |
| `organizationId` | `?string` | The `org` claim, when the instance issues one. |
| `claims` | `array<string,mixed>` | The full verified id_token + userinfo claim set. |
| `accessToken` | `string` | Call Cbox ID APIs on the user's behalf. |
| `refreshToken` | `?string` | When a refresh token was issued. |
| `idToken` | `?string` | The raw id_token (e.g. for RP-initiated logout hints). |
| `expiresIn` | `int` | Access-token lifetime in seconds. |

Reach any other claim with `$cbox->claim('some_key')`.

> **Key on `id`, never on email.** Email addresses change and can be reassigned; the
> subject is stable for the life of the account.

## Requesting different scopes

Override the configured scopes per login — e.g. to request an API scope you'll use
with the returned access token:

```php
CboxId::redirect(scopes: ['openid', 'profile', 'email', 'reports.read']);
```

## Handling failures

`authenticate()` throws — catch and route to your login page:

```php
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;

try {
    $cbox = CboxId::authenticate($request);
} catch (InvalidState) {
    // state didn't match — forged or stale callback; start over
    return redirect('/auth/redirect');
} catch (AuthenticationFailed $e) {
    report($e);
    return redirect('/login')->withErrors('Sign-in could not be completed.');
}
```

- `InvalidState` — the `state` did not match the session. Treat as a fresh start,
  not an error to show the user.
- `AuthenticationFailed` — the provider returned an error, the code was missing, or
  a token/`id_token` failed verification. Log it; show a generic message.

## Logout

If the instance advertises RP-initiated logout, end the Cbox ID session too:

```php
Route::post('/logout', function () {
    $url = CboxId::logoutUrl(returnTo: url('/'));
    auth()->logout();

    return $url ? redirect($url) : redirect('/');
});
```

`logoutUrl()` returns `null` when the instance advertises no end-session endpoint —
fall back to a local logout, as above.
