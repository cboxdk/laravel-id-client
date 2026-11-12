---
title: Kept permission decisions
description: Ask Cbox ID whether a person may do something without making pages wait on it — answers are kept, refreshed in the background, and still served while Cbox ID is down.
weight: 7
---

# Kept permission decisions

`PermissionDecisions::check()` asks Cbox ID live, every time. That is right for one
sensitive action, but a page that checks access on every view then waits on Cbox ID
on every view, and fails when Cbox ID is unreachable.

`KeptPermissionDecisions` keeps Cbox ID's answer instead. A page reads the last answer
from your cache, and a queued job asks Cbox ID again in the background when the answer
has gone stale. A change your user makes (a POST, PUT, PATCH or DELETE) still asks Cbox
ID when the kept answer is stale, so nobody acts on old access.

## Use it

Resolve `Cbox\Id\Client\Contracts\KeptPermissionDecisions` (bound to
`Cbox\Id\Client\Decisions\KeptDecisions`) and ask it. It fetches and keeps the
`decisions:read` machine token itself.

Before:

```php
$token = $this->identity->machineToken(['decisions:read']);
$decision = $this->decisions->check($token, $subject, $organizationId, ['invoices:approve']);
```

After:

```php
use Cbox\Id\Client\Contracts\KeptPermissionDecisions;

public function __construct(private KeptPermissionDecisions $decisions) {}

$decision = $this->decisions->check($subject, $organizationId, ['invoices:approve']);
abort_unless($decision->allowed(), 403);
```

The result is the same `PermissionDecision` that `PermissionDecisions::check()`
returns. The app's client must be allowed the `decisions:read` scope.

## How an answer is chosen

Every answer, yes and no, is kept with the time it was asked.

| Situation | What happens |
|---|---|
| No kept answer | Ask Cbox ID now and keep the answer. The first visit waits once. |
| Kept answer is fresh (younger than `fresh_for`, 30 s) | Use it, for any request. |
| Stale, and the request is a page view (GET, HEAD) | Use it at once, and queue `RefreshKeptDecision` to ask again. A changed role shows one page view later. Without a queue worker (the `sync` driver) the refresh runs after the response. |
| Stale, and the request is a change (POST, PUT, PATCH, DELETE) | Ask Cbox ID now. |
| Stale, and the caller passes `acting: true` | Ask Cbox ID now. |
| Older than `max_age` (thirty days) | Ask Cbox ID now. |
| Cbox ID said the person's access changed after the answer was kept | Ask Cbox ID now. |
| Outside an HTTP request (queue job, console command) | Always ask Cbox ID now. |

Pass `acting: true` when a GET does something on the answer, for example a download
link that releases a file:

```php
$decision = $this->decisions->check($subject, $organizationId, ['exports:download'], acting: true);
```

## When Cbox ID is down

- A page view with a kept answer (up to `max_age` old) gets that answer. The refresh
  job fails, reports the error and leaves the kept answer in place; the next stale
  page view queues another try.
- If the refresh job cannot even be queued (the queue is down), the error is reported
  and the page still gets the kept answer.
- A change with a stale answer, and anyone with no kept answer, gets the
  `AuthenticationFailed` that `PermissionDecisions::check()` throws. Turn it into a
  503 in your exception handler.

Every call to Cbox ID has a three-second connect timeout (`CBOX_ID_HTTP_CONNECT_TIMEOUT`),
so an unreachable Cbox ID fails fast.

## When access changes

A kept answer can be up to `fresh_for` seconds behind Cbox ID on a page view, and
the refresh is one view behind that.

The package ends kept answers itself when Cbox ID says access changed. The webhooks
that take access away from a person or an organization, and a back-channel logout, mark
them as changed; every answer kept before the mark is ignored and the next check asks.
The events, what the receiver needs, and the longest a revoked person can keep access
are in [What a request waits for](../core-concepts/local-first.md#when-access-is-taken-away).

When your own code learns about a change, say so:

```php
app(\Cbox\Id\Client\Decisions\KeptDecisions::class)->revokeSubject($subject);
app(\Cbox\Id\Client\Decisions\KeptDecisions::class)->revokeOrganization($organizationId);
```

Or drop one kept answer:

```php
$this->decisions->forget($subject, $organizationId, ['invoices:approve']);
```

To ask now and keep the answer (what a first visit and the job do), call
`refresh($subject, $organizationId, $permissions)`.

## Configuration

```dotenv
CBOX_ID_DECISIONS_FRESH_FOR=30        # seconds an answer is fresh
CBOX_ID_DECISIONS_MAX_AGE=2592000     # the oldest answer used at all (thirty days)
CBOX_ID_DECISIONS_STORE=redis         # a store every web server and worker shares
CBOX_ID_DECISIONS_QUEUE_CONNECTION=   # where RefreshKeptDecision runs; empty = default
CBOX_ID_DECISIONS_QUEUE=
```

Run a queue worker. With `QUEUE_CONNECTION=sync` the refresh runs right after the
page that noticed the stale answer, in the same request, which is fine locally but
makes that request wait.

## What is stored

Each entry is a plain array: the time it was asked, the subject, organization, client,
membership role, whether the organization is active, and each permission's yes or no.
No objects are unserialized and no tokens are stored. An entry that does not have
exactly that shape is ignored and asked again. The key is a hash of the client id,
subject, organization and the permission set; the order you list permissions in does
not matter.

The `decisions:read` machine token is kept by `machineToken()` (see
[Call Cbox ID APIs](call-cbox-id-apis.md#token-reuse)). If Cbox ID refuses it with a
401, it is dropped and the question is asked once more with a new token.

## Requests and the console

"In an HTTP request" means the app is not running in the console, or it is running
tests, or it is an Octane worker (`LARAVEL_OCTANE`). Queue workers and Artisan commands
always ask Cbox ID. To decide differently, extend `KeptDecisions`, override
`currentRequest()` and bind your class to `KeptPermissionDecisions`.

## Testing

`CboxId::fake()` replaces the decisions client and the identity client, and
`KeptPermissionDecisions` resolves them each time, so it works on top of the fake:

```php
$fake = CboxId::fake();
$fake->permissionDecisions()->queue(
    new PermissionDecision('user-1', 'org-1', 'cid_app', null, true, ['invoices:approve' => true]),
);

$this->get('/invoices')->assertOk();
```

The answer is kept in your test cache like any other, so a second check in the same
test reads it without consuming another queued outcome.
