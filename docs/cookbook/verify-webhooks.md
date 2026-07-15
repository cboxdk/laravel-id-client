---
title: Verify webhooks
description: Validate an inbound X-Cbox-Signature over the raw request body before you trust a webhook or inline-action call.
weight: 4
---

# Verify webhooks

When a Cbox ID instance calls *your* app — a webhook, or an inline action (an
external hook the platform invokes mid-flow) — it signs the request. Verify that
signature before acting on the payload.

## The check

`verifyWebhook()` validates the `X-Cbox-Signature` header — an HMAC-SHA256 over
`"{timestamp}.{raw body}"`, within a freshness window:

```php
use Cbox\Id\Client\Facades\CboxId;
use Illuminate\Http\Request;

Route::post('/webhooks/cbox-id', function (Request $request) {
    $ok = CboxId::verifyWebhook(
        payload: $request->getContent(),                 // RAW body — see below
        signatureHeader: $request->header('X-Cbox-Signature'),
        secret: config('services.cbox_id.webhook_secret'),
    );

    abort_unless($ok, 400);

    $event = $request->json()->all();
    // ... handle the verified event ...

    return response()->noContent();
});
```

## Use the raw body

Sign and verify the **exact bytes** you received. `$request->getContent()` gives the
raw body; a re-encoded array (`json_encode($request->all())`) can reorder keys or
change spacing and will fail verification even when the payload is genuine.

## What it checks — and rejects

`verifyWebhook()` returns `false` (never throws) when:

- the header is missing or malformed;
- the timestamp is missing, non-numeric, or outside the tolerance window
  (default **300 seconds** — tune via the `toleranceSeconds` argument);
- the HMAC doesn't match (compared with `hash_equals`, so the check is
  constant-time).

The freshness window is what stops a captured request from being replayed later.
Keep your server clock in sync (NTP) so legitimate calls aren't rejected as stale.

## The secret

The signing secret is issued when the webhook/action endpoint is registered on the
instance — it is **not** your OAuth `client_secret`. Store it wherever you keep other
inbound secrets (shown above as `services.cbox_id.webhook_secret`) and never commit
it.
