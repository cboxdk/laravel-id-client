---
title: Sign in by redirect
description: An app with no sign-in page of its own — /login, /signup, the launch and invitation links all redirect to Cbox ID's hosted sign-in and come back to your callback.
weight: 14
---

# Sign in by redirect

In redirect mode your app has no sign-in page. Somebody who is not signed in is sent
straight to Cbox ID's hosted sign-in and comes back to your callback; with a live Cbox ID
session they never see a page. Passwords and passkeys are typed on Cbox ID's origin only.

The default is still `embedded` (your app has its own sign-in page and the SDK registers
nothing), so upgrading changes nothing until you turn this on.

## Turn it on

```dotenv
CBOX_ID_SIGN_IN_MODE=redirect
```

```php
// routes/web.php
use Cbox\Id\Client\Facades\CboxId;

CboxId::signInRoutes();
```

That registers, in the `web` group:

| Route | Name | What it does |
|---|---|---|
| `GET /login` | `login` | Redirects to Cbox ID (authorization code + PKCE, `state`, `nonce`). Somebody already signed in goes to `sign_in.home`. |
| `GET /signup` | `signup` | The same, opening Cbox ID on "create an account" (`prompt=create`). |
| `GET /login/launch` | `login.launch` | A sign-in bound to `?organization=`, even when somebody is signed in. For the app switcher and links from other apps. |
| `GET /login/invitation` | `login.invitation` | Where an invitation brings somebody back to: a sign-in bound to `?organization=`. |
| `GET /login/error` | `login.error` | The page a failed sign-in ends on. |
| `GET /login/signed-out` | `login.signed-out` | "You are signed out." |

The name `login` is the one Laravel's `auth` middleware sends guests to, and it keeps the
page they asked for: after the callback, `redirect()->intended()` lands there.

Your **callback stays yours**, because that is where you make your own account and
session. Nothing about it changes, except where a failure goes (below).

## What a link may carry

Every entry point reads the same query parameters and passes on only what is well-formed:

| Parameter | Sent to Cbox ID as | Notes |
|---|---|---|
| `return_to` | — | A path in your app (`/orgs/acme/settings`) to land on afterwards. Another origin is ignored. |
| `organization` | `organization` | Binds the sign-in to that organization. The person must be an active member, or the callback gets `access_denied`. Not sent with sign-up: Cbox ID refuses the two together. |
| `login_hint` (or `email`) | `login_hint` | An address to prefill. |
| `prompt` | `prompt` | On `/login` only: `login`, `select_account` or `select_organization`. |
| `idp_hint` | `idp_hint` | On `/login` only: go straight to that social provider (`google`, `github`). |

Sign-up: when the instance's discovery document lists the prompts it supports and
`create` is not among them, sign-up is closed there and `/signup` is an ordinary sign-in.
`CboxId::offersSignUp()` answers the same question for your own "Create an account" link.

## The callback: send failures to the error page

There is no sign-in page to show an error on, and going back to `/login` would start the
same sign-in again. With a live Cbox ID session that is a loop nobody sees. Hand the
failure to `signInFailed()`:

```php
use Cbox\Id\Client\Exceptions\CboxIdException;

Route::get('/login/callback', function (Request $request) {
    try {
        $cbox = CboxId::authenticate($request);
    } catch (CboxIdException $e) {
        report($e);

        return CboxId::signInFailed($e);
    }

    $user = User::updateOrCreate(['cbox_id' => $cbox->id], ['email' => $cbox->email, 'name' => $cbox->name]);
    auth()->login($user);
    $request->session()->regenerate();

    return redirect()->intended('/');
});
```

`signInFailed()` redirects to `/login/error` carrying only the error code (`access_denied`,
`invalid_scope`, …), never Cbox ID's description of how your deployment is registered. A
state that did not match is a stale or repeated callback, not a refusal: that one goes back
to `/login` to start over.

When **your own** rules refuse somebody after a successful sign-in (not admitted, no
access to this app), answer with your own page or a `403`. Do not send them to `/login`.

As a second line of defence, `/login` counts the sign-ins that came back to your callback.
After `sign_in.max_round_trips` (3) within a minute it shows the error page instead of
starting another, whatever your callback does.

## Signing out

Landing on `/login` after a local logout signs the person straight back in while their
Cbox ID session is alive. Either end that session too:

```php
Route::post('/logout', function (Request $request) {
    $url = CboxId::logoutUrl(returnTo: route('login.signed-out'));
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect($url ?? route('login.signed-out'));
});
```

or keep the logout local and land on `login.signed-out`. `returnTo` must be registered on
the application as a sign-out URI; see [Log in users](log-in-users.md#logout).

## Content-Security-Policy

Nothing in the browser talks to Cbox ID any more, so its origin leaves `connect-src`.

Keep it in `form-action` if any `<form>` in your app is answered with a redirect to Cbox ID.
Browsers apply `form-action` to the redirects a form submission follows, and a `POST
/logout` that ends the Cbox ID session is exactly that. Keep it in `img-src` if you show
the app switcher: its icons are served by Cbox ID.

## Settings

All under `cbox-id-client.sign_in`:

| Key | Default | |
|---|---|---|
| `mode` | `embedded` (`CBOX_ID_SIGN_IN_MODE`) | `redirect` turns the routes on. |
| `login_path`, `signup_path`, `launch_path`, `invitation_path`, `error_path`, `signed_out_path` | `/login`, `/signup`, `/login/launch`, `/login/invitation`, `/login/error`, `/login/signed-out` | `null` leaves that route to your app. |
| `middleware` | `['web']` | |
| `guard` | `null` | The auth guard that says whether somebody is signed in. |
| `home` | `/` | Where somebody already signed in lands when they open `/login`. |
| `view` | `cbox-id-client::sign-in.notice` | The view of the error and signed-out pages. |
| `max_round_trips` | `3` | Sign-ins that may come back within a minute before `/login` stops. |

Keep a route for yourself by setting its path to `null`: an app whose sign-up is a waitlist
keeps its own `/signup`, and one whose launch does more than start a sign-in keeps its own
`/login/launch`.

### Your own notice page

The default page is plain and self-contained (no script, nothing from another origin; its
one `<style>` carries Vite's CSP nonce when there is one). To draw it in your layout, point
`sign_in.view` at your own view. It receives `kind` (`error` or `signed_out`), `code`,
`title`, `message`, `app`, `signInUrl`, `otherAccountUrl` and `nonce`. The texts go through
`__()`, so a JSON language file translates them.

## Moving an app from an embedded form

1. Set the mode and add `CboxId::signInRoutes()` **before** your own routes.
2. Delete your `/login` and `/signup` routes, controllers, pages and their assets, and
   whatever posted credentials to the Frontend API. Set a path to `null` for a route you keep.
3. Send callback failures to `CboxId::signInFailed($e)` and logout to `login.signed-out`.
4. Remove `CBOX_ID_PUBLISHABLE_KEY` if sign-in was its only use, and Cbox ID's origin from
   `connect-src`.
5. In tests, `CboxId::fake()` works unchanged: `GET /login` now answers with a redirect to
   the fake issuer instead of a page.
