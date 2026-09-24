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
use Cbox\Id\Client\Enums\AssignableMemberRole;

// Sign-up: the organization and its Owner in one call.
$org = CboxIdManagement::createOrganization(new NewOrganization(
    name: $request->company,
    ownerUserId: CboxId::principal()->subjectId(),
));

// Invite a colleague with your app's roles, and bring them back to your app after.
CboxIdManagement::invite($org->id, new NewInvitation(
    email: 'ada@example.com',
    role: AssignableMemberRole::Member,          // admin or member — never owner
    roles: ['editor'],                           // your manifest keys, with clientId
    returnTo: route('welcome'),
    clientId: config('cbox-id-client.client_id'),
    inviterName: $request->user()->name,
));

// A role by id — or by your manifest key plus the app that declared it.
CboxIdManagement::assignRole($org->id, $userId, 'editor', config('cbox-id-client.client_id'));

// Ownership only ever moves; the previous owner stays on as admin.
CboxIdManagement::transferOwnership($org->id, $newOwnerId);   // organizations:write
```

Every method names the scope it needs (`organizations:write` — which also covers
ownership transfer — `members:write`, `invitations:write`, `roles:write`, …). A few
answers worth knowing: `resendInvitation()` returns a NEW invitation whose id replaces
the old one; `archiveOrganization()` archives (status `deleted`) rather than erases;
`updateApi()` with `scopes` replaces the whole set, and `unlinkClient: true` detaches the
API from its app. The request and response shapes are checked against Cbox ID's own
OpenAPI document in this package's test suite. Refusals are typed: `ResourceNotFound`,
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

## 6. Staff roles and support sessions

### Staff roles: yours, not your customers'

Some roles belong to **your** team, not to anyone in a customer's organization — support,
billing operations, trust & safety. Declare them in your manifest as staff-only:

```php
// config/cbox-id-client.php
'authz' => [
    'permissions' => [
        ['key' => 'parcels:read', 'description' => 'View parcels'],
        ['key' => 'support:impersonate', 'description' => 'Act as a customer'],
    ],
    'roles' => [
        ['key' => 'viewer', 'name' => 'Viewer', 'permissions' => ['parcels:read']],
        ['key' => 'support', 'name' => 'Support', 'tenant_assignable' => false,
            'permissions' => ['parcels:read', 'support:impersonate']],
    ],
],
```

and publish (`php artisan cbox-id:publish-manifest`). A staff role is:

- **never offered to a customer** — an organization's own administrators, invitations
  and directory mappings cannot hand it out. Your backend, with the environment's
  authority, still can: `assignRole()` may grant a staff role at one customer (your
  support lead at a key account);
- **held environment-wide** — granted to a person across the whole environment rather
  than in one organization:

  ```php
  $app = config('cbox-id-client.client_id');

  CboxIdManagement::grantEnvironmentRole($staffUserId, 'support', $app);   // by manifest key
  CboxIdManagement::hasEnvironmentRole($staffUserId, 'support', $app);
  CboxIdManagement::environmentRoles($staffUserId);                       // everything they hold
  CboxIdManagement::revokeEnvironmentRole($staffUserId, 'support', $app);
  ```

- **per app** — a role your app declared, granted environment-wide, shows up only in
  YOUR app's tokens, never in another app's in the same environment.

`tenant_assignable` must be a real boolean: the SDK refuses to publish `"false"` as a
string, because Cbox ID would reject the manifest and, read leniently, it would make a
staff role assignable by every tenant. Declaring no staff role leaves your manifest's
checksum exactly what it was, so existing apps do not re-sync.

In your app, a staff member's roles and permissions arrive in the token like anyone
else's, so `hasPermission('support:impersonate')` and `@can` work unchanged.

### Support sessions

A staff member holding your app's `support:impersonate` permission through an
environment-wide grant can sign in to your app AS a customer's user — reason required
(shown to the customer), an hour at most, no refresh token, audited on both sides. Send
a registered `redirectUri` and a PKCE challenge to get the first authorization code back:

```php
use Cbox\Id\Client\Management\Data\NewSupportSession;

$verifier = bin2hex(random_bytes(32));
$session = CboxIdManagement::startSupportSession(new NewSupportSession(
    userId: $customerId,
    organizationId: $orgId,
    clientId: config('cbox-id-client.client_id'),
    actorUserId: $staff->cbox_id,
    reason: 'Ticket #4711: invoice totals look wrong',
    redirectUri: route('auth.callback'),
    codeChallenge: rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
));

// $session->code — redeem it at the token endpoint with $verifier and the redirect URI.
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
app, their organization and a subset of their permissions. Link them there:

```blade
<a href="{{ CboxId::apiKeysUrl() }}">Manage API keys</a>
{{-- apiKeysUrl(clientId: …, returnTo: …, organization: …) — defaults: this app, this page --}}
```

`apiKeysUrl()` opens `{issuer}/account/api-keys` with your app preselected and a link back
to the page the person was on (shown only for an origin your app registered). Then protect
the API:

```php
Route::middleware('cbox-id.api-key:reports:read')->get('/v1/reports', ReportIndex::class);
```

The key arrives as `Authorization: Bearer ctx_live_…`. Cbox ID re-caps its permissions
at every verify, so a member who loses a permission cannot keep it through a key. The
answer is cached for `CBOX_ID_API_KEY_CACHE_TTL` seconds (default 60) — that is how
long a revoked key keeps working; set 0 to ask every time. A dead key is a 401, a
missing permission a 403, and an unreachable Cbox ID a **503** with `Retry-After` —
never a 401, which would tell a customer to rotate a key that works.

Outside a route: `CboxId::verifyApiKey($key, ['reports:read'])` — the other half of
`CboxId::apiKeysUrl()`: one sends people to make keys, the other checks what they send.

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

## 9. Signing out everywhere

When a person signs out of Cbox ID, or loses access, Cbox ID can tell your app to end
their sessions too — see [Back-channel logout](back-channel-logout.md).

## Testing all of it

`CboxId::fake()` gives you every piece above without a fake issuer — see
[Testing](../getting-started/testing.md).
