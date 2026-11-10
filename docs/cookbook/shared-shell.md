---
title: Organizations in the shared shell
description: The shell's Organization level on your local data, the token following the URL's organization, and "New organization" — with cboxdk/laravel-console-kit.
weight: 20
---

# Organizations in the shared shell

Every Cbox app renders one shell, described by `cboxdk/laravel-console-kit`. Its first
context level is always the organization. This package provides that level, and the
middleware that keeps the person's token on the organization the URL names.

console-kit is suggested, not required. Without it nothing here is registered except the
`OrganizationDirectory` binding and the `cbox-id.org-url` middleware.

## Wire it

```php
use Cbox\Console\Kit\Facades\Console;
use Cbox\Id\Client\Shell\OrganizationLevel;

Console::shell()->levels(app(OrganizationLevel::class), new ProjectLevel, new EnvironmentLevel);
```

```php
// routes/web.php
Console::shellRoutes();

Route::middleware(['web', 'auth', 'cbox-id.org-url', 'console.shell'])
    ->prefix('orgs/{org}')
    ->group(function () {
        // the organization's pages
    });
```

The URL standard puts the organization behind `/orgs/{slug}`, with its Cbox ID slug. The
paths are in `cbox-id-client.shell`:

| Key | Default | Use |
|---|---|---|
| `route_parameter` | `org` | The route parameter that carries the slug. |
| `organizations_path` | `/orgs` | "View all", and each organization at `/orgs/{slug}`. |
| `details_path` | `/orgs/{slug}/settings` | Where "New organization" lands. |

## Local data only

The level and the middleware read `Contracts\OrganizationDirectory`: the person's
organizations from the app's own data. Neither calls Cbox ID while a page renders.

The default, `SessionOrganizationDirectory`, reads the `organizations` claim the person
signed in with (and the bound `org`). An organization joined since then shows up after
the next sign-in or switch, and a slug the claim does not carry falls back to the id. An
app that mirrors memberships by webhook should bind its own directory:

```php
$this->app->bind(OrganizationDirectory::class, MirroredOrganizations::class);
```

Answer only organizations the person is an active member of. The shell resolves every id
and slug through it, and refuses anything it does not return.

## The token follows the URL

Switching organization in the shell changes the URL only. On the next page,
`cbox-id.org-url` compares the URL's organization with the one the person's session is
bound to:

- **Same organization:** the page renders.
- **Not a member** (the directory does not know the slug): `404`.
- **Another organization the person belongs to:** the middleware stores the URL as the
  intended URL and calls `switchOrganization()`. With a live Cbox ID session the person
  sees no prompt. Your callback ends with `redirect()->intended()`, so they land on the
  same URL, now bound to the new organization. An Inertia visit gets `409` with
  `X-Inertia-Location`, so the browser does this as a full page load. A JSON request gets
  `409 {error: "organization_rebind", location}`.
- **Still bound elsewhere within a minute** (Cbox ID refused the switch): `403`, not a
  loop.
- **A support session** is pinned to its organization: `403`.

`cbox-id.org-url:team` reads another route parameter.

## New organization

"New organization" in the menu asks only for a name.

- **With a management key** (`CBOX_ID_MANAGEMENT_KEY` with `organizations:write`): the
  organization is created with the person as Owner, kept in the directory
  (`remember()`), and the answer is `{id, detailsUrl}` with `details_path`. A name Cbox ID
  refuses is a `422` on `name` with its message.
- **Without one:** the answer is `{id: null, detailsUrl}`, where `detailsUrl` is Cbox ID's
  hosted "create an organization" step. The shell sends the person there as a full page
  load; they come back to `/orgs`. The hosted step asks for the name again.
