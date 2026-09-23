---
title: "Multi-tenant apps: teams, invites, roles, staff, API keys"
description: Build a team-based SaaS on Cbox ID — organizations and their tiers, roles and permissions, switching teams, invitations, staff and support sessions, and your customers' API keys.
weight: 7
---

# Multi-tenant apps: teams, invites, roles, staff, API keys

Your customers sign up as **organizations** (teams). People are **members** of one or
more of them, with a built-in **tier** (Owner, Admin, Developer, Member, Viewer). On top
of that, your app declares its own **roles** and **permissions** in its manifest, and
Cbox ID resolves which ones each person holds in each organization.

All of it arrives in the token. This recipe is how to read it, enforce it, and change it.

> **Needs a Cbox ID instance with the tenancy API** (`org_role` and `act` claims, the
> organization prompts, the environment-API tenancy endpoints and API-key
> verification — laravel-id 1.19 or later). On an older instance the claims are simply
> absent, and every check below answers "no".

## 1. Who is this, and for which organization?

Every sign-in, bearer token and customer API key is a
`Cbox\Id\Client\Contracts\Principal`. Ask it the same questions whichever it is:

```php
use Cbox\Id\Client\Facades\CboxId;

$principal = CboxId::principal();          // null when nobody is signed in

$org = $principal?->organization();        // id, name, role — or null
$org?->role;                               // OrganizationRole::Admin (the `org_role` claim)
$org?->canManage();                        // Owner or Admin
$principal?->hasPermission('invoices:create');
$principal?->roles();                      // your manifest roles in this organization
$principal?->isSupportSession();           // staff acting as this person (see §6)
```

After `authenticate()`, the SDK remembers this in the session (never the tokens), bound
to the local user your callback logs in. A different login or a logout forgets it, so
nobody inherits somebody else's permissions. It is what Cbox ID said **at sign-in**; see
[refreshing](#8-keeping-permissions-fresh) for keeping it current.

## 2. Require an organization, a tier, a permission

```php
// routes/web.php
Route::middleware(['auth', 'cbox-id.org'])->group(function () {
    Route::get('/dashboard', DashboardController::class);

    Route::middleware('cbox-id.org:admin')->get('/team/settings', TeamSettings::class);
    Route::middleware('cbox-id.permission:invoices:create')->post('/invoices', StoreInvoice::class);
});
```

- `cbox-id.org` — the caller must act for an organization. A browser session without
  one is sent to Cbox ID's hosted **organization picker** and back to the page it asked
  for (turn that off with `CBOX_ID_ORGANIZATION_PICKER=false`); JSON gets a 403
  `organization_required`. The `Organization` is then injectable into your controller.
- `cbox-id.org:admin` — that tier **or higher**. An unknown tier is not enough.
- `cbox-id.permission:a,b` — every permission named.

They work the same behind `cbox-id.token` (a bearer token) and `cbox-id.api-key` (a
customer key), so one route file can serve your UI and your API.

### Use Laravel's own authorization

Turn on the permission gate (`CBOX_ID_GATE=true`) and every `feature:action` ability is
answered from the principal's permissions:

```blade
@can('invoices:create')
    <a href="{{ route('invoices.create') }}">New invoice</a>
@endcan
```

```php
$this->authorize('invoices:create');
$request->user()->can('invoices:export');
```

It only ever **grants**. A permission the person lacks falls through to your own gates
and policies, and abilities that are not `feature:action` are never touched. Keep
row-level questions ("is this invoice in their organization?") in a policy.

There are no Blade directives beyond `@can`: it already covers permissions, and
`CboxId::principal()` covers the rest.

## 3. Switching and creating teams

The organization is part of the token, so switching is a new authorization — invisible
to someone with a live Cbox ID session:

```php
Route::post('/teams/{id}/switch', fn (string $id) => CboxId::switchOrganization($id));
Route::get('/teams/choose', fn () => CboxId::selectOrganization());   // hosted picker
Route::get('/teams/new', fn () => CboxId::createOrganization());      // hosted "create a team"
```

Your existing callback finishes the job: `authenticate()` checks that Cbox ID bound the
sign-in to the organization you asked for (a person who is not a member gets
`access_denied`, carried on `AuthenticationFailed::isAccessDenied()`), and the session
remembers the new organization.

To draw a switcher, ask for the `organizations` scope (add it to `CBOX_ID_SCOPES` and
allow it on your app); `$user->organizations()` then lists every team with its tier.

## 4. Provisioning from your backend

Create an **environment API key** (`cbid_env_…`) with the scopes you need and set
`CBOX_ID_MANAGEMENT_KEY`. It can provision every tenant in the environment — keep it on
the server.

```php
use Cbox\Id\Client\Facades\CboxIdManagement;
use Cbox\Id\Client\Management\Data\{NewOrganization, NewInvitation};
use Cbox\Id\Client\Enums\OrganizationRole;

// Sign-up: the organization and its Owner in one call.
$org = CboxIdManagement::createOrganization(new NewOrganization(
    name: $request->company,
    ownerUserId: CboxId::principal()->subjectId(),
));

// Invite a colleague with your app's roles, and bring them back to your app after.
CboxIdManagement::invite($org->id, new NewInvitation(
    email: 'ada@example.com',
    role: OrganizationRole::Member,
    roles: ['editor'],
    returnTo: route('welcome'),
    clientId: config('cbox-id-client.client_id'),
));

CboxIdManagement::assignRole($org->id, $userId, $roleId);
CboxIdManagement::transferOwnership($org->id, $newOwnerId);
```

Every method names the scope it needs (`organizations:write`, `members:write`,
`invitations:write`, `roles:write`, …). Refusals are typed: `ResourceNotFound`,
`ValidationFailed` (with `$e->errors` per field) or `ManagementApiException` with the
stable `$e->error` code and `isForbidden()` / `isRateLimited()`.

## 5. Keep your app in sync with webhooks

```php
use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\Webhooks\EventType;

CboxIdWebhooks::on(EventType::MembershipCreated, fn ($e) => Seats::allocate($e->organizationId, $e->string('user_id')));
CboxIdWebhooks::on(EventType::MembershipDeleted, fn ($e) => Seats::release($e->organizationId, $e->string('user_id')));
CboxIdWebhooks::on(EventType::ApiKeyRevoked,     fn ($e) => /* forget any cached key */);
```

`$event->sequence` goes up by one per delivery to your endpoint: a jump means one is
still in flight or was lost, and it lets you put retried deliveries back in order. See
[verify webhooks](verify-webhooks.md) for the receiver itself.

## 6. Staff and support sessions

Declare staff-only roles in your manifest with `"tenant_assignable": false` — they can
be held environment-wide but are never offered inside a customer's organization. Grant
one to a member of your team:

```php
CboxIdManagement::grantEnvironmentRole($staffUserId, $roleId);
```

A staff member holding your app's `support:impersonate` permission can start a support
session for a customer — reason required, an hour at most, no refresh token, audited on
both sides:

```php
use Cbox\Id\Client\Management\Data\NewSupportSession;

CboxIdManagement::startSupportSession(new NewSupportSession(
    userId: $customerId, organizationId: $orgId, clientId: config('cbox-id-client.client_id'),
    reason: 'Ticket #4711: invoice totals look wrong',
));
```

Tokens minted for it carry the `act` claim. Show it, and refuse what support must never
do on a customer's behalf:

```php
if (CboxId::principal()?->isSupportSession()) {
    abort_if($request->routeIs('account.password', 'account.delete'), 403);
    // $principal->actor()->subject is the member of staff — record it in your audit log.
}
```

## 7. Your customers' API keys

Your customers mint keys for **your** API in Cbox ID's hosted UI, each bound to your
app, their organization and a subset of their permissions. Protect the API:

```php
Route::middleware('cbox-id.api-key:reports:read')->get('/v1/reports', ReportIndex::class);
```

The key arrives as `Authorization: Bearer ctx_live_…`. Cbox ID re-caps its permissions
at every verify, so a member who loses a permission cannot keep it through a key. The
answer is cached for `CBOX_ID_API_KEY_CACHE_TTL` seconds (default 60) — that is how
long a revoked key keeps working; set 0 to ask every time. A dead key is a 401, a
missing permission a 403, and an unreachable Cbox ID a **503** with `Retry-After` —
never a 401, which would tell a customer to rotate a key that works.

Outside a route: `CboxId::verifyApiKey($key, ['reports:read'])`.

## 8. Keeping permissions fresh

The session holds what was true at sign-in. Ask for `offline_access` to get a refresh
token, then:

```php
$tokens = CboxId::refresh($storedRefreshToken, remember: true);
$store->put($tokens->refreshToken);   // ALWAYS — Cbox ID rotates and detects reuse
```

`remember: true` re-reads the person's organization tier, roles and permissions into the
session. `AuthenticationFailed::isInvalidGrant()` means the session is over and they
must sign in again; anything else is worth retrying.

## Testing all of it

`CboxId::fake()` gives you every piece above without a fake issuer — see
[Testing](../getting-started/testing.md).
