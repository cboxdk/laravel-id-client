---
title: App catalog (Switch app)
description: Serve the person's apps to the app switcher with one line, and keep the grant behind it renewed on the server so nobody ever clicks "reconnect".
weight: 13
---

# App catalog (Switch app)

The app switcher lists the apps a person can open in their organization. Cbox ID
publishes that list per person. The SDK reads it with the person's own grant and serves
it to your page as same-origin JSON. It also keeps that grant alive on the server, so
nobody has to click "reconnect".

## Turn it on

```dotenv
CBOX_APP_CATALOG_ENABLED=true
CBOX_ID_SCOPES="openid profile email organizations offline_access"
```

```php
// routes/web.php
CboxId::appCatalogRoutes();
```

That is all. Your app's OAuth client in Cbox ID needs:

- `https://<your-app>/apps/callback` as a redirect URI;
- the `account:apps:read` scope and token exchange allowed;
- `offline_access` allowed, if you want renewal without any page navigation (see below).

It also needs a server-side session driver (database, redis, file). The catalog is
refused on the cookie driver, because the grant would end up in the browser. Routes that
block the session need a cache with locks, which those drivers already have.

## What happens at sign-in

With the catalog on, every login asks for `account:apps:read` as well. When your callback
calls `CboxId::authenticate($request)`, the SDK keeps two things, sealed in the session:

1. the login access token, until it expires;
2. the person's refresh token, when the token response had one.

That is all the callback does for the catalog. It makes no extra call to Cbox ID, so an
app switch with a live Cbox ID session stays a plain SSO hop: one call to the token
endpoint, plus the discovery, keys and UserInfo the login itself needs.

The first catalog read trades the login token for a catalog grant (RFC 8693 token
exchange, bound to a fresh DPoP key) and drops the login token.

If you build the `CboxUser` some other way, call it yourself:

```php
CboxId::rememberAppCatalogSignIn($user);
```

## The routes

| Route | Name | What it does |
| --- | --- | --- |
| `GET /apps/catalog` | `cbox-id.apps.catalog` | The catalog as JSON. |
| `GET /apps/authorize` | `cbox-id.apps.authorize` | Starts the browser flow for a grant. |
| `GET /apps/callback` | `cbox-id.apps.callback` | Finishes it. |
| `GET /apps/signin` | `cbox-id.apps.signin` | Starts your app's sign-in and comes back to `return_to`. |

The first three need a signed-in person (`web` and `auth` by default). The sign-in route
must work without one, so it has only `web`. Paths and middleware are under
`cbox-id-client.app_catalog` in the config.

The catalog answers:

- `200` `{apps: [{key, name, description, launch_url, icon_url, icon_dark_url}], revision, refresh_after}`.
  The apps are in the order Cbox ID returns them; the SDK does not sort them.
- `401` `{signin_url}` when only a new sign-in can get a grant. The page navigates there
  on its own; there is no button. With a live Cbox ID session the person sees a short
  redirect and lands back on the same page.
- `503` `{error: "catalog_unavailable"}` when Cbox ID cannot answer right now. Try again
  after a while; a sign-in would not help.

Every answer is `Cache-Control: private, no-store`. The grant itself never reaches the
browser.

## Renewal without a click

When the grant is missing or expires within five minutes, the catalog route gets a new
one on the server, under a cache lock per session:

1. if the login access token is still valid, exchange it, then drop it;
2. otherwise refresh the person's Cbox ID tokens with the kept refresh token, store the
   new refresh token at once (Cbox ID rotates it on every use), and exchange the new
   access token;
3. otherwise answer `401` with `signin_url`.

The lock makes two tabs renew one at a time. That matters: Cbox ID treats a refresh token
presented twice as stolen, and revokes the whole family. The newest refresh token and
grant are also kept in the cache, keyed by the session, because a page request that
started before a renewal writes its older copy of the session back when it ends. Under
the lock the cache copy is read first, so the token presented is always the newest one.

If the renewal fails while the old grant is still valid, the old grant is used and the
renewal is tried again on the next read. When renewal is impossible (no refresh token,
Cbox ID refused it, or the page is about another organization than the one the person
signed in to), the answer is `401` with `signin_url`.

### Without `offline_access`

Cbox ID gives out a refresh token only when the login asked for `offline_access`. The SDK
does not add that scope for you. Without it, the first grant still comes from the login
token, but there is nothing to renew it with on the server. When it expires the catalog
answers `401` with `signin_url`, and the page makes one trip through sign-in. No click is needed, but the page does reload. Add
`offline_access` to `CBOX_ID_SCOPES` to avoid that.

If your app already keeps the login's refresh token and refreshes it itself, do not let
both do it: the second one to refresh presents a spent token. Keep one owner.

## Organizations from the URL

By default the catalog is for the organization the person signed in to (the session's
remembered identity). If your URL decides the organization instead, bind your own
resolver:

```php
use Cbox\Id\Client\AppCatalog\AppCatalogContext;
use Cbox\Id\Client\Contracts\ResolvesAppCatalogContext;
use Illuminate\Http\Request;

$this->app->bind(ResolvesAppCatalogContext::class, fn () => new class implements ResolvesAppCatalogContext {
    public function resolve(Request $request): ?AppCatalogContext
    {
        $user = $request->user();
        $team = /* the team the page is about, checked against the user's memberships */;

        return $user && $team ? new AppCatalogContext($user->cbox_id, $team->cbox_id) : null;
    }
});
```

Return `null` and the routes answer `403`. A refresh token only renews grants for the
organization the person signed in to. For any other organization the answer is `401` with
a `signin_url` that names that organization, and the sign-in binds to it.

## Your own sign-in launch

The SDK's sign-in route stores `return_to` as the intended URL and redirects to Cbox ID.
Your callback must end with `redirect()->intended(...)` for the person to land back on the
page. If your app has its own launch route, name it instead; it gets `return_to` and
`organization` query parameters:

```dotenv
CBOX_APP_CATALOG_SIGNIN_ROUTE=login.launch
```

## Logout

Laravel's `Logout` event forgets the grant, the cached list and the refresh token, in
the session and in the cache.

## Checks

A grant is kept only when it is signed by the issuer, is for exactly this person,
organization and client, has no actor (`act`), has exactly the scope `account:apps:read`,
has the audience `{issuer}/mcp`, and is bound to the DPoP key the SDK generated. A
support session gets no catalog. A refresh token is bound to the person it was issued
to and is never used for anyone else.
