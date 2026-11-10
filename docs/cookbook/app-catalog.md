---
title: App catalog (Switch app)
description: Serve the person's apps to the app switcher with one line. The app reads the catalog with its own token, so nobody ever signs in again or clicks "reconnect" for it.
weight: 13
---

# App catalog (Switch app)

The app switcher lists the apps a person can open in their organization. Cbox ID
publishes that list per person. The SDK reads it with your app's own machine token and
serves it to your page as same-origin JSON. Nothing about the catalog lives in the
person's session, so there is no grant to expire, renew or reconnect.

## Turn it on

```dotenv
CBOX_APP_CATALOG_ENABLED=true
```

```php
// routes/web.php
CboxId::appCatalogRoutes();
```

That is all. Your app's OAuth client in Cbox ID needs the `client_credentials` grant and
the `app_catalog:resolve` scope.

`CboxId::appCatalogRoutes()` only registers routes. It does not build the identity
client, so a test that sets the Cbox ID config after the routes load still gets that
config.

## How the catalog is read (machine mode)

`machine` is the default `app_catalog.mode` (`CBOX_APP_CATALOG_MODE`). For each read the
catalog route:

1. resolves whose catalog it is (person and organization, see
   [Organizations from the URL](#organizations-from-the-url));
2. answers from the app's cache when it has a fresh answer for that person and
   organization;
3. otherwise calls `GET {issuer}/api/v1/app-catalog/for-user?user_id=…&organization_id=…`
   with the app's cached client-credentials token (`machineToken(['app_catalog:resolve'])`),
   with a 1 second connect and 2 second total timeout. With `CBOX_INTERNAL_ROUTES` set,
   the call goes in-cluster like every other SDK call.

An answer is fresh for Cbox ID's `refresh_after`, at least 60 seconds. It is cached per
issuer, client, person and organization in the catalog's cache store
(`CBOX_APP_CATALOG_CACHE_STORE`, the default store when empty). The last good answer is
kept for 24 hours.

The cache comes first. When Cbox ID errors, rate-limits or times out, the route serves the
last good answer, still `200`, with a short `refresh_after` (30 seconds). Only when
there is no earlier answer does it answer `503 {error: "catalog_unavailable"}`, which the
switcher retries quietly.

When Cbox ID answers `404`, the person is no longer an active member of the organization.
The kept answer is dropped and the route answers `401 {signin_url}`. That is the only
case where machine mode sends anyone to sign in.

Login is not touched. Sign-in asks for no extra scope, and `authenticate()` does no
catalog work. This works on any session driver, the cookie driver included.

### While Cbox ID has not given the client `app_catalog:resolve`

When the token request for `app_catalog:resolve` is refused (`invalid_scope`,
`unauthorized_client`), or the catalog endpoint answers `403` `insufficient_scope` or
`invalid_scope`, the read falls back to grant mode (below) for that request. A warning
is logged at most once an hour, with the text "Give the client the app_catalog:resolve scope".
For the next five minutes the machine read is not tried again, so the token endpoint is
not asked on every request.

While the fallback has been in use within the last hour, sign-ins prepare a grant as in
grant mode: login asks for `account:apps:read` and the callback keeps the login token.
Give the client the scope in Cbox ID and the machine read takes over within five
minutes. Sign-ins stop asking for `account:apps:read` an hour later at most.

## Grant mode

Set `CBOX_APP_CATALOG_MODE=grant` to keep the 0.19.0 flow, where the catalog is read with
the person's own grant. The client then needs:

- `https://<your-app>/apps/callback` as a redirect URI;
- the `account:apps:read` scope and token exchange allowed;
- `offline_access` allowed, if you want renewal without any page navigation (see below).

Grant mode also needs a server-side session driver (database, redis, file). The catalog
is refused on the cookie driver, because the grant would end up in the browser. Routes
that block the session need a cache with locks, which those drivers already have.

### What happens at sign-in

In grant mode, every login asks for `account:apps:read` as well. When your callback
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

In machine mode it does nothing and returns `false`.

### Renewal without a click

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

#### Without `offline_access`

Cbox ID gives out a refresh token only when the login asked for `offline_access`. The SDK
does not add that scope for you. Without it, the first grant still comes from the login
token, but there is nothing to renew it with on the server. When it expires the catalog
answers `401` with `signin_url`, and the page makes one trip through sign-in. No click is needed, but the page does reload. Add
`offline_access` to `CBOX_ID_SCOPES` to avoid that.

If your app already keeps the login's refresh token and refreshes it itself, do not let
both do it: the second one to refresh presents a spent token. Keep one owner.

## The routes

| Route | Name | What it does |
| --- | --- | --- |
| `GET /apps/catalog` | `cbox-id.apps.catalog` | The catalog as JSON. |
| `GET /apps/authorize` | `cbox-id.apps.authorize` | Starts the browser flow for a grant (grant mode). |
| `GET /apps/callback` | `cbox-id.apps.callback` | Finishes it (grant mode). |
| `GET /apps/signin` | `cbox-id.apps.signin` | Starts your app's sign-in and comes back to `return_to`. |

The first three need a signed-in person (`web` and `auth` by default). The sign-in route
must work without one, so it has only `web`. Paths and middleware are under
`cbox-id-client.app_catalog` in the config.

The catalog answers:

- `200` `{apps: [{key, name, description, launch_url, icon_url, icon_dark_url, purpose, hue, admission_url, enabled}], revision, refresh_after}`.
  The apps are in the order Cbox ID returns them; the SDK does not sort them. By default
  only the organization's own apps (`enabled: true`) are in the answer, so a switcher that
  reads `launch_url` as a string keeps working. `?include=available` adds the apps it does
  not have yet; cbox-ui's switcher (through console-kit's shell) asks for them.

Each app carries:

| Field | Meaning |
|---|---|
| `enabled` | `true` for an app the organization has. `false` for one it does not have yet: the switcher shows it dimmed under "More from Cbox". |
| `launch_url` | Where an enabled app opens. `null` for an app that is not enabled. |
| `admission_url` | For an app that is not enabled: its signup or admission page, in the same organization. |
| `purpose` | One line for the tile ("Identity and sign-in"), or `null`. |
| `hue` | The app's fixed colour: a hex colour (`#4f46e5`) or an OKLCH hue angle (`258`). Anything else is dropped. |

All four are optional on Cbox ID's side. An older Cbox ID that sends none of them answers
enabled apps only, with `purpose`, `hue` and `admission_url` null. An enabled app without
a `launch_url`, or one that is not enabled without an `admission_url`, makes the answer
malformed. A catalog cached by an older SDK version is read again.
- `401` `{signin_url}` when only a new sign-in can help: in machine mode, the person is
  no longer a member; in grant mode, no grant can be had on the server. The page navigates
  there on its own; there is no button. With a live Cbox ID session the person sees a
  short redirect and lands back on the same page.
- `503` `{error: "catalog_unavailable"}` when Cbox ID cannot answer right now and there
  is no earlier answer. Try again after a while; a sign-in would not help.

Every answer is `Cache-Control: private, no-store`. No token reaches the browser.

### At most one sign-in trip per five minutes

The page is sent to sign in at most once per five minutes. The mark is set when a
sign-in actually starts: when the browser opens `/apps/signin`, or when a grant-mode
sign-in completes in `authenticate()`, which also covers your own launch route. A `401`
the page did not follow sets nothing, so the next read answers `401` again. Within five
minutes of a sign-in, a read that still needs one answers `503` instead, because another
trip would not help and could loop. After five minutes it answers `401` again.

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

Return `null` and the routes answer `403`. In machine mode any organization the person is
a member of works. In grant mode a refresh token only renews grants for the organization
the person signed in to. For any other organization the answer is `401` with a
`signin_url` that names that organization, and the sign-in binds to it.

## Your own sign-in launch

The SDK's sign-in route stores `return_to` as the intended URL and redirects to Cbox ID.
Your callback must end with `redirect()->intended(...)` for the person to land back on the
page. If your app has its own launch route, name it instead; it gets `return_to` and
`organization` query parameters:

```dotenv
CBOX_APP_CATALOG_SIGNIN_ROUTE=login.launch
```

## Logout

Laravel's `Logout` event forgets the grant-mode grant, the cached list and the refresh
token, in the session and in the cache. The machine-mode cache holds only the published
list of apps, and it is kept per person and organization.

## Checks

Every catalog answer is checked before it is served or cached: app keys, names, HTTPS
launch and admission URLs, the hue, and icons served by the issuer. A malformed answer counts as Cbox ID being
unavailable.

In grant mode, a grant is kept only when it is signed by the issuer, is for exactly this person,
organization and client, has no actor (`act`), has exactly the scope `account:apps:read`,
has the audience `{issuer}/mcp`, and is bound to the DPoP key the SDK generated. A
support session gets no catalog. A refresh token is bound to the person it was issued
to and is never used for anyone else.
