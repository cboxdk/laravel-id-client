# cboxdk/laravel-id-client

Laravel/PHP **consumer** SDK for Cbox ID — the package a *product* installs to
authenticate its users against a running Cbox ID instance (the opposite end from
[`cboxdk/laravel-id`](../laravel-id), which *is* the identity platform).

It speaks standard OpenID Connect, so integrating is a login redirect and a callback —
not a rewrite — with PKCE, CSRF `state`, a nonce, and full id_token signature/issuer/
audience verification handled for you. It adds the two conveniences a hosted-identity
product needs: a **redirect to the instance's hosted profile-management page**, and
back-channel helpers (**machine tokens, userinfo, introspection, revocation, webhook
verification**).

Part of **Cbox ID** — the self-hostable, Laravel-native identity platform. MIT licensed.

## Migrating off an old login

While you move users to Cbox ID, it can ask your system whether an email and password it
has never seen are good — and import that person on the yes. You write the one function
that knows your database; the handler owns the signature, the freshness window and the
constant-time compare:

```php
use Cbox\Id\Client\Migration\{LegacyLogin, LegacyUser};

Route::post('/cbox-legacy', LegacyLogin::using(function (string $email, string $password): ?LegacyUser {
    $row = DB::connection('legacy')->table('users')->where('email', $email)->first();

    return $row && Hash::check($password, $row->password)
        ? new LegacyUser($row->email, $row->name, $row->confirmed_at !== null, $row->password)
        : null;
}));
```

Set `CBOX_ID_LEGACY_SECRET` to at least 32 characters. `LegacyLogin::using()` refuses to
build without it — at boot, where somebody is looking, rather than as a 500 that reads as
an outage.

Return `null` for "wrong password". **Throwing is different**: it means your store could
not decide, and is answered with 503 so Cbox ID refuses the sign-in rather than reading an
outage as a bad credential. Returning the stored hash lets the person keep their password
verbatim; omit it and Cbox ID hashes the one they just proved they know.

No route is registered for you, deliberately: unlike webhooks, this endpoint receives
passwords, so where it lives and what sits in front of it should be a decision somebody
made rather than a default they inherited.

## Install

```bash
composer require cboxdk/laravel-id-client
php artisan vendor:publish --tag=cbox-id-client-config
```

Requires PHP `^8.4` and Laravel 12 or 13.

> **Where do these values come from?** Register an app in your environment console and
> answer **"Web app"** to *what kind of app is this?* — a Laravel app runs on a server and
> can keep a secret, so that is the kind that gets one. See
> [Integrate your app](https://github.com/cboxdk/cbox-id/blob/main/docs/getting-started/integrate-your-app.md).

Configure the instance and your OAuth client (registered on the Cbox ID instance):

```dotenv
CBOX_ID_ISSUER=https://acme.cboxid.com
CBOX_ID_CLIENT_ID=cid_...
CBOX_ID_CLIENT_SECRET=csec_...
CBOX_ID_REDIRECT=https://app.acme.com/auth/callback
```

Every endpoint (authorize, token, userinfo, jwks) is discovered from the issuer, so
that's usually all you configure.

## Log a user in

```php
use Cbox\Id\Client\Facades\CboxId;

// routes/web.php
Route::get('/auth/redirect', fn () => CboxId::redirect());          // → Cbox ID login

Route::get('/auth/callback', function (\Illuminate\Http\Request $request) {
    $cbox = CboxId::authenticate($request);   // verifies state, PKCE, id_token

    $user = User::updateOrCreate(
        ['cbox_id' => $cbox->id],                              // the stable `sub`
        ['email' => $cbox->email, 'name' => $cbox->name],
    );

    auth()->login($user);
    return redirect('/dashboard');
});
```

`authenticate()` returns a `CboxUser` — `id` (subject), `email`, `name`,
`organizationId`, the full verified `claims`, and the `accessToken` / `refreshToken`.
It throws `InvalidState` on a forged/stale callback and `AuthenticationFailed`
otherwise — with the server's `error` and `error_description` on it. Everything the SDK
throws extends `Cbox\Id\Client\Exceptions\CboxIdException`.

An empty `CBOX_ID_ISSUER` (or client id, or redirect) is refused with `NotConfigured`,
which names the missing environment variable and renders as a **503**, not a 500. Put
`cbox-id.configured:issuer,client_id,redirect` in front of your login routes to refuse
before anything runs.

## Teams, roles and permissions

The organization a person acts for, their tier in it, and your app's roles and
permissions arrive in the token. Read them off any principal — a sign-in, a bearer
token, a customer API key:

```php
$principal = CboxId::principal();

$principal->organization()?->role;             // OrganizationRole::Admin
$principal->hasPermission('invoices:create');
$principal->isSupportSession();                // staff acting as this person
```

Enforce them on routes, or through Laravel's own `@can` with `CBOX_ID_GATE=true`:

```php
Route::middleware(['auth', 'cbox-id.org:admin'])->get('/team/settings', …);
Route::middleware(['auth', 'cbox-id.permission:invoices:create'])->post('/invoices', …);
```

Switch teams with `CboxId::switchOrganization($id)` (or `selectOrganization()` /
`createOrganization()` for Cbox ID's hosted steps), provision with `CboxIdManagement`
(an environment API key), and protect your API with your customers' own keys via
`cbox-id.api-key:reports:read`. The whole walkthrough is
[Multi-tenant apps](docs/cookbook/multi-tenant-apps.md).

## Feature flags

Request the `feature_flags` scope and the person's flags arrive in the token. Ask any
principal, guard a route, or branch a view:

```php
CboxId::principal()?->hasFeature('new-dashboard');

Route::middleware(['auth', 'feature:new-dashboard'])->get('/dashboard', …);
```

```blade
@feature('new-dashboard') <x-new-dashboard /> @else <x-dashboard /> @endfeature
```

A missing claim means every feature is off. From a job, ask
`CboxIdApi::environment()->featureFlags->evaluate([...])`. Fine-grained authorization
(`CboxIdApi::environment()->fga->check([...])`) and leasing a person's GitHub, Google or
Slack token (`CboxId::leasePipeToken('github', …)`, with typed exceptions carrying the
connect URL) are in
[Feature flags, fine-grained authorization and Pipes](docs/cookbook/feature-flags-fga-and-pipes.md).

## Back-channel logout

Set `CBOX_ID_BACKCHANNEL_LOGOUT=true` and register `/cbox-id/backchannel-logout` as your
app's back-channel logout URI: when a person signs out of Cbox ID, their sessions here end
too. Logout tokens are validated strictly (signature, issuer, audience, freshness, events,
no nonce, replay). Sessions are deleted at once on the database and redis drivers, and end
on the next request on the cookie driver. See
[Back-channel logout](docs/cookbook/back-channel-logout.md).

## Refresh tokens

Ask for `offline_access` (in `CBOX_ID_SCOPES`), then:

```php
$tokens = CboxId::refresh($refreshToken);   // persist $tokens->refreshToken — it rotates
```

`AuthenticationFailed::isInvalidGrant()` means the person must sign in again.

## Testing

```php
$cbox = CboxId::fake();

$cbox->actingAs('user_1', organization: 'org_1', permissions: ['invoices:create']);
$this->withToken((string) $cbox->token()->permissions(['reports:read']))->getJson('/api/reports');
$cbox->management()->assertInvited('ada@example.com');
```

No fake issuer: tokens are really signed and really verified. See
[Testing](docs/getting-started/testing.md).

## Draw your own sign-in box

Reading the environment's public configuration from PHP lets a Blade page render a sign-in
box in the customer's own branding — with no JavaScript SDK, and no flash of unstyled form
while one loads. A **publishable** key is the opposite of the client secret above: public on
purpose, and useful only from the origins its owner listed against it.

```php
// config/cbox-id-client.php — CBOX_ID_PUBLISHABLE_KEY
use Cbox\Id\Client\Frontend\FrontendClient;

$config = app(FrontendClient::class)->config();
$acme = app(FrontendClient::class)->config('acme');   // the buttons acme's hosted page shows

$config->endpoint('authorization');  // where the form posts on to
$config->social;                     // the buttons this environment has enabled
$config->accent();                   // the customer's brand colour
$config->isLive();                   // false for a pk_test_ key — draw the badge
```

And who is signed in, given a token you already hold:

```php
$session = app(FrontendClient::class)->session($accessToken);

$session->signedIn();          // false is a state, not an error
$session->user?->initials();   // 'AL' — for the avatar fallback
```

The key grants nothing on its own: `session()` is authorized by the token, and `config()`
answers the same document to everybody. The configuration is cached for a minute
(`CBOX_ID_FRONTEND_CACHE_TTL`) because it decides layout and a page render is not a good
place for a network call.

**Before it works:** an operator turns the Frontend API on (`CBOX_ID_FRONTEND_API=true` —
it is off by default) and mints a key under **Developers → Frontend keys**, listing the
origins allowed to use it. Exact matches only: `https://acme.com` does not cover
`https://www.acme.com`.

## Send users to hosted profile management

Let users manage their own password, MFA, passkeys and sessions on the instance's
hosted account page, then come back to your app:

```php
Route::get('/account', fn () => CboxId::redirectToProfile(returnTo: route('dashboard')));
// or just the URL: CboxId::profileUrl(route('dashboard'))
```

## Call Cbox ID APIs

```php
$token   = CboxId::machineToken(['api.read']);       // client-credentials (M2M)
$claims  = CboxId::userinfo($accessToken);           // OIDC userinfo
$active  = CboxId::introspect($token)['active'];      // RFC 7662
CboxId::revoke($refreshToken, 'refresh_token');      // RFC 7009
```

Revoking a refresh token drops the whole token family — that's what "sign out
everywhere" needs.

## Verify a webhook / action

```php
$ok = CboxId::verifyWebhook(
    payload: $request->getContent(),                 // the RAW body
    signatureHeader: $request->header('X-Cbox-Signature'),
    secret: config('services.cbox.webhook_secret'),
);
abort_unless($ok, 400);
```

For an endpoint on the Standard Webhooks scheme (`signature_scheme: standard_webhooks`), use
`CboxId::verifyStandardWebhook($request->getContent(), $request->headers->all(), $secret)`
with its `whsec_…` secret. The built-in receiver accepts either scheme.

## Receive provisioning webhooks (outbound provisioning)

Instead of standing up a SCIM server, register a hook and let the SDK verify and
route Cbox ID's signed events. Set `CBOX_ID_WEBHOOK_SECRET`, then in a service
provider's `boot()`:

```php
use Cbox\Id\Client\Facades\CboxIdWebhooks;

CboxIdWebhooks::on('organization.member_added', fn ($e) => Seat::allocate($e->string('user_id')));
CboxIdWebhooks::on('organization.member_removed', fn ($e) => Seat::release($e->string('user_id')));
CboxIdWebhooks::on('role.assigned', fn ($e) => /* … */);
CboxIdWebhooks::on('*', fn ($e) => Log::info('cbox event', ['type' => $e->type]));
```

`Cbox\Id\Client\Webhooks\EventType` names every catalogued event
(`CboxIdWebhooks::on(EventType::MembershipCreated, …)`), and `$event->sequence` goes up by
one per delivery to your endpoint, so a gap is visible.

The SDK mounts a signed receiver at `POST /cbox-id/webhooks` (configurable). Register
that URL as a webhook endpoint on your Cbox ID instance (Developers → Webhooks),
subscribe it to the event types you handle, and copy its signing secret into
`CBOX_ID_WEBHOOK_SECRET`. Signature verification (HMAC-SHA256, replay-bounded) and JSON
parsing are handled for you; a bad or stale signature is rejected before anything runs.

**The receiver is slim.** It verifies, acknowledges immediately, and runs your handlers
on a queued job (`ProcessCboxIdWebhook`) — so a slow handler never stalls the response
or trips the dispatcher's timeout/retry. Point `CBOX_ID_WEBHOOK_QUEUE_CONNECTION` /
`CBOX_ID_WEBHOOK_QUEUE` at a real async queue in production (with `QUEUE_CONNECTION=sync`
the job runs inline). Each event's `deliveryId` is stable, so dedupe retries with it.

## Management API

Typed clients for Cbox ID's four management planes, **generated from the OpenAPI documents
the server publishes** (vendored in `openapi/`), so every method, path, scope and type matches
the server. Server-side only: every client holds a management credential.

| Client | Plane | Credential | Host |
| --- | --- | --- | --- |
| `EnvironmentClient` | One environment's tenancy: organizations, users, apps, roles, SSO, audit logs… | `cbid_env_…` key, or a delegated token | The environment's own host (or the root, with `environment:`) |
| `WorkspaceClient` | The workspace above it: projects, environments, team, keys | `cbid_ws_…` key, or a person's root token | `https://api.cboxid.com` (default) |
| `PlatformClient` | The deployment itself, for operators | Delegated operator token only | `https://api.cboxid.com` (default) |
| `AccountClient` | A person's own account | Delegated token only | The environment's own host |

Method names are the server's action names — `apps.secrets.rotate` is
`$env->apps->secrets->rotate(…)` — with path parameters first, then the body (or query) array,
then `CallOptions`. Results are readonly schema objects inside an `ApiResponse` (`data`,
`status`, `replayed`, `idempotencyKey`, `requestId`, `meta`, `body`); bodies are arrays whose
shape PHPStan checks.

```php
use Cbox\Id\Client\Facades\CboxIdApi;          // config: CBOX_ID_MANAGEMENT_KEY, CBOX_ID_WORKSPACE_KEY
use Cbox\Id\Client\Management\EnvironmentClient;

$env = CboxIdApi::environment();                // or: new EnvironmentClient(baseUrl: 'https://acme.cboxid.com', apiKey: $key)

$app = $env->apps->create(['name' => 'Billing', 'type' => 'web', 'redirect_uris' => [$callback]])->data;
$app->clientSecret;                             // in this response and no other — store it now

$secret = $env->apps->secrets->rotate($app->id, ['grace_seconds' => 3600])->data;

foreach ($env->organizations->listAll(['status' => 'active']) as $organization) {
    // every page, fetched lazily
}

$created = CboxIdApi::workspace()->environments->create(['name' => 'Staging', 'type' => 'sandbox'])->data;
```

- **Idempotent writes.** Every POST/PUT/PATCH/DELETE carries an `Idempotency-Key` (a UUID, or
  `new CallOptions(idempotencyKey: …)`). Network failures, 5xx, 429 and
  `409 idempotency_in_progress` are retried with the **same** key, honouring `Retry-After`.
  `$response->replayed` is true when the server answered from its idempotency store.
- **Approvals.** A call a key's policy holds (`202 approval_required`) is waited on: the client
  tells `onApprovalRequired` (or dispatches `ManagementApprovalRequired` through `CboxIdApi`),
  polls the approval on its own host, and repeats the request with `Cbox-Approval` and the same
  key. `ApprovalDenied` / `ApprovalExpired` when it does not get one. Pass
  `CallOptions::returnPendingApproval()` to get a `PendingApprovalResult` back instead, and
  `resume()` it later.
- **Errors.** `CboxIdApiException` with `status`, `error` (the stable code), `errors` (field
  errors on `validation_failed`), `requestId` and `retryAfter`; `ManagementNetworkException`
  (with the `idempotencyKey`) when no answer came at all.
- **Audit Logs.** `CboxIdApi::auditLogger()->record([...])` buffers events and sends batches
  of up to 100, each under its own key (flushed when the app terminates);
  `CboxIdApi::auditLogs()->export($filters)` waits for a CSV export; `AuditChain::verify()`
  recomputes an organization's hash chain byte-for-byte as the server does.

The whole guide — delegated tokens, `Cbox-Environment` from the platform root, pagination,
audit logs, and regenerating — is [Management API](docs/cookbook/management-api.md).
`composer generate` regenerates the clients from `openapi/`; the test suite fails when the
generated code and the specs disagree.

## License

MIT © Cbox.
