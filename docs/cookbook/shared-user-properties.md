---
title: Shared user property events
description: Receive verified preference and metadata changes and connect them to your app's cache and private realtime channels.
weight: 12
---

# Shared user property events

The built-in webhook receiver dispatches Laravel's
`Cbox\Id\Client\Events\UserPropertiesUpdated` for both
`user.preferences.updated` and `user.metadata.updated`. The event runs inside
`ProcessCboxIdWebhook`, after signature and payload validation. Existing
`CboxIdWebhooks::on(...)` and wildcard handlers still run.

Enable the [webhook receiver](verify-webhooks.md), configure its signing secret,
and run a worker for its configured queue. A synchronous queue runs listeners in
the request; use a durable asynchronous connection in production. Subscribe the
endpoint to the two event types in ID and explicitly grant it access to each
namespace. A wildcard subscription does not grant namespace access.

## Listen in the application

Register in your application's service provider:

```php
use App\Listeners\RefreshSharedUserProperties;
use Cbox\Id\Client\Events\UserPropertiesUpdated;
use Illuminate\Support\Facades\Event;

Event::listen(UserPropertiesUpdated::class, RefreshSharedUserProperties::class);
```

The listener receives these readonly fields:

| Field | Meaning |
| --- | --- |
| `issuer` | Configured ID issuer, including its environment path |
| `eventId` | Domain event ID; stable across redeliveries |
| `userId` | ID subject, not necessarily the app's local user primary key |
| `namespace` | `preferences` or a custom sharing namespace |
| `changedKeys` | List of changed top-level keys, including removed keys |
| `version` | New document version, scoped to this issuer/user/namespace |
| `clientId` | Writing OAuth client, or null for server-originated changes |
| `occurredAt` | Source event's timestamp |
| `webhook` | Original verified envelope, including type, delivery ID and sequence |

This is an invalidation notification, not a document. There are no property
values in the ID payload. Do not treat `changedKeys` as a patch.

## Refresh, then broadcast

Your app's `RefreshSharedUserProperties::handle(UserPropertiesUpdated $event)`
should:

1. Resolve the local person by issuer and ID subject. Do not assume the ID subject
   equals the local primary key or select a person from a browser-supplied ID.
2. Deduplicate using issuer + event ID and serialize updates to issuer + subject +
   namespace. Skip versions already applied. Do not mark an event complete before
   its refresh and durable follow-up have succeeded.
3. Fetch `GET /oauth/users/{user_id}/properties/{namespace}` with the receiver's
   own authorized credentials. It needs `user_properties:read`, explicit namespace
   access and the correct issuer audience; DPoP-bound tokens need their proof.
4. Atomically apply only a newer fetched document to the local mirror. The fetched
   version may be newer than the event's version. Treat revoked grants as revoked
   access, not a temporary outage.
5. Dispatch your app's own broadcast event with the applied namespace and version,
   through a private channel authorized to that person and environment. Use a
   transaction/outbox or equivalent retry-safe mechanism so a crash after saving
   the mirror cannot permanently lose the broadcast.

The package event does **not** implement `ShouldBroadcast`: the SDK cannot know
your app's local user mapping, channel names, namespace permissions or WS provider.
Never broadcast the raw SDK event or arbitrary metadata on an organization-wide or
public channel. An app-owned `ShouldBroadcast` event can send only
`namespace` and `version`; the browser then fetches the authorized local mirror.

The webhook worker retries when a synchronous listener throws. Async listeners
must configure their own retry policy. Delivery is **at least once**, not exactly
once; the SDK does not suppress duplicate or out-of-order events on your behalf.
Even a change from the receiving client must reach its other open sessions.

## Browser recovery and writes

Refresh on login, reconnect and focus when stale; a disconnected browser can miss
a broadcast. Apply only newer versions and clear state on logout/account changes.
Receiving a change must not write it back to ID.

Explicit user edits use
`PATCH /oauth/users/{user_id}/properties/{namespace}` with
`{"version":4,"set":{"theme":"dark"},"remove":[]}`.
Save centrally first, then mirror the returned document. Reconcile a 409 against
the latest document rather than overwriting unrelated keys. Reset a preference
with `remove`, not null. The reserved preferences include `theme`, `language`,
`locale`, `timezone`, `date_format`, `time_format` and `week_start`.

Read/write HTTP calls, local persistence, deduplication and broadcasts remain app
responsibilities; this addition supplies the verified Laravel event hook.
