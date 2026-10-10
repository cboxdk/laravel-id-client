---
title: Feature flags, fine-grained authorization and Pipes
description: Read Cbox ID feature flags on the user, a route and a Blade block; check fine-grained relationships with consistency tokens; and lease a person's GitHub, Google or Slack token.
weight: 10
---

# Feature flags, fine-grained authorization and Pipes

Three things your app can ask Cbox ID instead of storing itself: which features are on for
this person, whether this person may do something to **this** record, and a working access
token for an account the person connected at another service.

Needs Cbox ID with laravel-id 1.24 or later.

## Feature flags

### From the token

Give the app the **Their feature flags** scope (`feature_flags`) on its Scopes tab in the
console, and request it at sign-in:

```php
// config/cbox-id-client.php
'scopes' => ['openid', 'profile', 'email', 'feature_flags'],
```

The ID token, the access token and UserInfo then carry the keys of every flag that is on
for the person in the organization they signed in to. Read them off the user, a verified
bearer token, or the identity the session remembers:

```php
$user = CboxId::authenticate($request);

$user->featureFlags();              // ['acme-beta', 'new-dashboard'], or null
$user->hasFeature('new-dashboard'); // true

CboxId::principal()?->hasFeature('billing.v2'); // on any later request of the session
```

`null` means the claim is absent — the scope was not requested — and `[]` means nothing is
on. Either way `hasFeature()` is `false`: a forgotten scope turns every feature off, never
on. A token carries the flags as they were when it was issued; a refresh with
`remember: true` picks up a change. A customer API key carries no flags.

### On a route

```php
Route::middleware(['auth', 'feature:new-dashboard'])->get('/dashboard', …);
Route::middleware(['cbox-id.token', 'cbox-id.feature:billing.v2,acme-beta'])->get('/api/billing', …);
```

Every flag named must be on. A flag that is off answers **404** — the route is not there
for this person yet, and a 403 would tell them it exists. A guest gets the usual 401 or
login redirect.

### In a view

```blade
@feature('new-dashboard')
    <x-new-dashboard />
@else
    <x-dashboard />
@endfeature
```

`feature` (the middleware alias) and `@feature` (the directive) are the short names, set
under `feature_flags` in the config. Laravel Pennant registers `@feature` too: if you use
both, rename one (`'blade' => 'cboxFeature'`) or set it to `null`. A middleware alias your
app already registered is never replaced; `cbox-id.feature` always works.

### From your backend, without a token

A queue job or a webhook handler has no token in hand. Ask the management API with a key
that has the **Read feature flags** scope:

```php
$answer = CboxIdApi::environment()->featureFlags->evaluate([
    'user_id' => $userId,
    'organization_id' => $organizationId,
])->data;

$answer->featureFlags;               // exactly what the claim would carry
$answer->evaluations[0]->reason;     // 'user_target', 'organization_target', 'rollout', 'default' or 'disabled'
```

### In tests

```php
CboxId::fake()->actingAs('user_1', featureFlags: ['new-dashboard']);

$this->get('/dashboard')->assertOk();
```

`signIn(featureFlags: […])` queues a sign-in that carries them.

## Fine-grained authorization

Roles and permissions answer "may this person approve invoices in this organization?".
Fine-grained authorization answers "may this person edit **this** document?". Your backend
writes relationship tuples and asks checks through the generated environment client, with
a key that has `fga:read` and `fga:write`:

```php
use Cbox\Id\Client\Fga\FgaTuple;

$fga = CboxIdApi::environment()->fga;

$written = $fga->tuples->write(['tuples' => [[
    'resource_type' => 'document', 'resource_id' => 'leave', 'relation' => 'viewer',
    'subject' => ['type' => 'user', 'id' => 'alice'],
]]])->data;

// At least as fresh as that write — pass the token when the very next request must see it.
$allowed = $fga->check([
    'resource_type' => 'document', 'resource_id' => 'leave', 'relation' => 'viewer',
    'subject_type' => 'user', 'subject_id' => 'alice',
    'consistency_token' => $written->consistencyToken,
])->data->allowed;

// Up to 100 at once, answered in order at one revision:
$results = $fga->checkBatch(['checks' => [
    FgaTuple::check('document', 'leave', 'viewer', 'user', 'alice'),
    'document:readme#editor@user:alice',
]])->data->results;
```

`$fga->tuples->delete()`, `$fga->resources->list()` (which documents can alice view),
`$fga->subjects->list()` (who can view this one) and `$fga->schema->get()` / `update()` /
`validate()` complete it. `FgaTuple::format()` writes the notation from the same array
`tuples->write()` takes, and refuses a part the notation cannot carry (`#`, `@`,
whitespace).

## Pipes: a person's own GitHub, Google or Slack token

When a person has connected their account at a provider — under **My account › Connected
services**, or through the connect page you send them to — lease a fresh access token for
it whenever you call that provider as them. Cbox ID refreshes it first when it is about to
expire, so you never handle a refresh token:

```php
use Cbox\Id\Client\Exceptions\PipeNotConnected;
use Cbox\Id\Client\Exceptions\PipeReauthorizationRequired;
use Cbox\Id\Client\Exceptions\PipeTemporarilyUnavailable;

try {
    $token = CboxId::leasePipeToken('github', purpose: 'list-repos', userId: $user->cbox_id);
} catch (PipeNotConnected|PipeReauthorizationRequired $e) {
    return redirect($e->connectUrlWith(config('cbox-id-client.client_id'), url()->current()));
} catch (PipeTemporarilyUnavailable $e) {
    return back()->with('status', "GitHub is not answering; try again in {$e->retryAfter} seconds.");
}

$repos = Http::withToken($token->accessToken)
    ->accept('application/vnd.github+json')
    ->get('https://api.github.com/user/repos')
    ->json();
```

Without `accessToken:` the lease is made as your app, with a client-credentials token
scoped `vault.lease`, and `userId` names the person. With a token issued to your app
**for** the person, pass it as `accessToken:` and leave `userId` out. Use the token, drop
it, and lease again next time.

| Exception | Status | What to do |
| --- | --- | --- |
| `PipeNotConnected` | 404 | Send the person to `$e->connectUrl` (`connectUrlWith()` adds your app and a way back) |
| `PipeReauthorizationRequired` | 409 | The same: the provider stopped accepting the connection |
| `PipeTemporarilyUnavailable` | 503 | Retry after `$e->retryAfter` seconds |
| `PipeLeaseDenied` | 403 | The app is not granted the pipe, or the person is outside its organization |

All four extend `PipeLeaseFailed`, which is also what any other refusal is.

To send somebody to connect before any lease:

```php
return CboxId::redirectToPipeConnect('github', route('settings.integrations'));
```

They come back with `?provider=github&status=connected` (or `cancelled`, `failed`).
