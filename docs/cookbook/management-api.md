---
title: Management API
description: The typed, generated clients for Cbox ID's environment, workspace, platform and account planes — idempotent writes, approvals, pagination, errors and Audit Logs.
weight: 9
---

# Management API

`Cbox\Id\Client\Management` holds a typed client for each of Cbox ID's management planes. They
are **generated from the OpenAPI documents the server publishes** (vendored in `openapi/`), so
every method, path, scope and type matches the server they were generated from. Use them from
server code only: every client holds a management credential.

| Client | Plane | Credential | `baseUrl` |
| --- | --- | --- | --- |
| `EnvironmentClient` | One environment's tenancy: organizations, users, apps, roles, SSO, audit logs… | `cbid_env_…` key, or a delegated access token | The environment's own host, or the platform root with `environment:` |
| `WorkspaceClient` | The workspace above its environments: projects, environments, team, keys | `cbid_ws_…` key, or a person's root token | `https://api.cboxid.com` (default) |
| `PlatformClient` | The deployment itself, for operators | Delegated operator token only | `https://api.cboxid.com` (default) |
| `AccountClient` | A person's own account | Delegated token only | The environment's own host |

A key for the wrong plane (`cbid_ws_…` on the environment plane, any key on the platform or
account plane) is refused when the client is built, not answered with a 401 on every request.
`baseUrl` must be https (loopback http is allowed for development); `/api/v1` is appended.

> This is separate from the older `CboxIdManagement` facade (`Contracts\Management`), which
> keeps working unchanged. The generated clients cover every operation of every plane.

## Configure it in Laravel

```dotenv
CBOX_ID_ISSUER=https://acme.cboxid.com
CBOX_ID_MANAGEMENT_KEY=cbid_env_...     # environment plane, on the issuer's host
CBOX_ID_WORKSPACE_KEY=cbid_ws_...       # workspace plane, on CBOX_ID_ROOT_URL
CBOX_ID_ROOT_URL=https://api.cboxid.com # the platform root (self-hosted: your root host)
```

```php
use Cbox\Id\Client\Facades\CboxIdApi;

CboxIdApi::environment();                       // EnvironmentClient, key from config
CboxIdApi::workspace();                         // WorkspaceClient, key from config
CboxIdApi::environmentAs($token, 'acme-staging'); // as a person, from the root host
CboxIdApi::platform($operatorToken);
CboxIdApi::account($personToken);
```

`EnvironmentClient` and `WorkspaceClient` also resolve from the container. Without the key, the
first use throws `NotConfigured` naming the environment variable. `management.retries`,
`management.timeout` and `management.approval_poll_interval` tune the transport.

Outside Laravel's container, build a client yourself:

```php
use Cbox\Id\Client\Management\EnvironmentClient;

$env = new EnvironmentClient(
    baseUrl: 'https://acme.cboxid.com',
    apiKey: $key,                                // or accessToken: $token / fn () => $tokens->current()
    onApprovalRequired: fn ($approval, $context) => $this->info("Approve {$context->action}: {$approval->bindingCode}"),
);
```

## Calling operations

Method names are the server's action names (`x-action`): `apps.secrets.rotate` is
`$env->apps->secrets->rotate(…)`, and `sso.connections.require_sso` is
`$env->sso->connections->requireSso(…)`. Arguments are the path parameters, in path order;
then the JSON body (or, for a read, the query) as an array; then `CallOptions`.

```php
$response = $env->apps->create([
    'name' => 'Billing',
    'type' => 'web',
    'redirect_uris' => ['https://billing.acme.com/callback'],
]);

$app = $response->data;            // Environment\Schemas\App — readonly, camelCase properties
$app->clientSecret;                // in this response and no other: store it now
$response->requestId;              // quote it when reporting a problem
$app->toArray();                   // back to the wire form (snake_case)
```

Body and query arrays carry PHPStan array shapes generated from the spec (required keys,
enum values, nested objects), so a typo or a missing required field fails static analysis.
Results are readonly schema classes per plane (`Environment\Schemas\Organization`,
`Workspace\Schemas\CreatedEnvironment`, …); a free-form object is an `array<string, mixed>`,
and a `204` is `null`.

Every plane has an operation table — `Environment\Operations::spec('apps.secrets.rotate')` —
with the method, path, scope, `Danger` and whether the action can be held for approval:
useful for a confirmation before a `critical` action. `$env->request('POST', '/path', $query,
$body)` reaches a route the generated surface does not cover.

## Idempotency and retries

Every `POST`, `PUT`, `PATCH` and `DELETE` sends an `Idempotency-Key` — a fresh UUID, or your
own with `new CallOptions(idempotencyKey: $key)`. Network failures, `5xx`, `429` and
`409 idempotency_in_progress` are retried (three times by default, exponential backoff with
jitter) with the **same** key, so a write lands once. `Retry-After` is honoured; one longer
than `maxDelayMs` is thrown instead of waited out, with `retryAfter` set.

`$response->replayed` is true when the server answered from its idempotency store: this is
the first request's answer, and a secret in it (a client secret, a key's token) is `null` — it
was shown once, to the request that created it.

## Errors

| Exception | When |
| --- | --- |
| `CboxIdApiException` | The server answered with an error. `status`, `error` (the stable code — branch on this), `errors` (field-keyed, on `validation_failed`), `requestId` (the body's `request_id`, else `X-Request-Id`), `retryAfter`; `isValidationError()`, `isNotFound()`, `isForbidden()`, … |
| `ManagementNetworkException` | No answer after every retry. Carries the `idempotencyKey`: repeating the call with it is safe and tells you whether the write happened. |
| `UnexpectedResponse` | A success the client could not read as the spec promises. The write happened; `body` and `idempotencyKey` are on it. Regenerate against the server you run. |
| `ApprovalDenied`, `ApprovalExpired`, `ApprovalException` | See below. |

All extend `CboxIdException`. Request bodies are never put in an exception, and the client
never logs.

## Approvals

When a key's policy holds an action for a person's approval, the server answers
`202 approval_required`. By default the client tells `onApprovalRequired` (through
`CboxIdApi`, the `Events\ManagementApprovalRequired` event) so you can show
`$approval->bindingCode`, polls the approval — on the client's own origin only, because the
poll carries the credential — and repeats the request with `Cbox-Approval` and the same
`Idempotency-Key` once it is approved. `ApprovalDenied` and `ApprovalExpired` are thrown when it
is not.

To not wait in the same call:

```php
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;

$outcome = $env->apps->secrets->rotate($app->id, ['grace_seconds' => 0], CallOptions::returnPendingApproval());

if ($outcome instanceof PendingApprovalResult) {
    echo "Approve on your device. Code: {$outcome->approval->bindingCode}";
    $outcome = $outcome->resume();   // polls, then repeats the request
}

$outcome->data->clientSecret;
```

The return type says which you get: with `returnPendingApproval()` it is
`ApiResponse<AppSecret>|PendingApprovalResult<…>`; without, just `ApiResponse<AppSecret>`.

## One token for every environment

A person's access token issued at the platform root reaches any environment of their
workspace when the request names it with `Cbox-Environment`:

```php
$staging = CboxIdApi::environmentAs(fn () => $tokens->current(), environment: 'acme-staging');
$staging->organizations->portalLinks->create($orgId, ['intents' => ['sso', 'dsync']]);
```

What the token may do there is bounded by the person's role and the token's scopes. A
`cbid_env_…` key is bound to its own environment's host, so `environment:` is refused with one.

## Lists

A paged list returns a `Page` — iterate it, or read `items`, `hasMore`, `nextCursor`
(environment plane) and `nextPage` (workspace plane). Every paged list also has an `…All()`
twin, a `Generator` that fetches pages as you reach them:

```php
foreach ($env->organizations->listAll(['status' => 'active']) as $organization) {
    // $organization is an Environment\Schemas\Organization
}
```

## Audit Logs

Your app records what its users did, per organization, and Cbox ID keeps each
organization's events in a tamper-evident hash chain.

```php
CboxIdApi::auditLogger()->record([
    'organization_id' => $org->id,
    'action' => 'invoice.voided',
    'actor' => ['id' => $user->id, 'type' => 'user', 'name' => $user->name],
    'targets' => [['id' => $invoice->id, 'type' => 'invoice']],
    'context' => ['location' => $request->ip(), 'user_agent' => $request->userAgent()],
    'metadata' => ['reason' => 'duplicate'],
]); // occurred_at defaults to now
```

`AuditLogger` buffers events and sends batches of up to 100, each under its own
`Idempotency-Key`: when a batch fills, on `flush()` / `close()`, and — for the container's
logger — when the application terminates. A batch that fails stays queued with the same key,
so sending it again never records an event twice. In a queue worker or a long-running
command, call `flush()` yourself.

```php
use Cbox\Id\Client\Management\AuditLogs\AuditChain;

$export = CboxIdApi::auditLogs()->export(['organization_id' => $org->id]); // waits until ready
$export->url;                                    // signed and short-lived: download it now

$check = CboxIdApi::auditLogs()->verifyChain($org->id);  // reads every event, checks here
$check->valid; $check->reason; $check->brokenAtSequence;

AuditChain::verify($events);                     // events you already hold
```

The verifier recomputes `sha256(prev_hash ‖ canonical JSON)` exactly as the server does —
proven in the test suite against a vector the server computed. It is tamper-evident, not
tamper-proof: it cannot see the chain's head, so it cannot tell whether events were removed
from the end; the server's `$env->auditLogs->verify()` can.

## Regenerating

The specs are vendored in `openapi/`. `composer generate` regenerates
`src/Management/{Environment,Workspace,Platform,Account}/` and the four clients from them;
`php bin/generate-management --fetch environment=https://acme.cboxid.com` (or `workspace=`,
`platform=`, `account=`, `all=`) refreshes a vendored spec from a running server first. The test
suite fails when the generated code and the specs disagree, and snapshots the generated surface
in `tests/Fixtures/management-surface.txt` so a changed method shows up in review.
