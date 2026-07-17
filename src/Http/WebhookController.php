<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Webhooks\ProcessCboxIdWebhook;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Cbox\Id\Client\Webhooks\WebhookHandlers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Cbox ID webhooks: verifies the HMAC signature (via IdentityClient) against
 * the shared secret, then ENQUEUES the event for the app's handlers and returns
 * immediately. Keeping the receiver slim — verify + ack, work off-thread — means a
 * slow handler can't stall the response or trip the dispatcher's timeout/retry. A
 * machine endpoint: no session or CSRF. 401 on a bad/replayed signature, 422 on a
 * malformed body, 200 once the event is accepted.
 */
final class WebhookController
{
    public function __construct(
        private readonly IdentityClient $identity,
        private readonly WebhookHandlers $handlers,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('cbox-id-client.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            // Fail closed: an unconfigured secret must never accept unverified events.
            return new JsonResponse(['error' => 'webhook_secret_not_configured'], 500);
        }

        $tolerance = is_numeric($t = config('cbox-id-client.webhooks.tolerance')) ? (int) $t : 300;
        $body = $request->getContent();

        if (! $this->identity->verifyWebhook($body, $request->header('X-Cbox-Signature'), $secret, $tolerance)) {
            return new JsonResponse(['error' => 'invalid_signature'], 401);
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! is_string($data['type'] ?? null)) {
            return new JsonResponse(['error' => 'invalid_payload'], 422);
        }

        $payload = [];
        foreach ((is_array($data['data'] ?? null) ? $data['data'] : []) as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        $event = new WebhookEvent(
            type: $data['type'],
            payload: $payload,
            organizationId: is_string($payload['organization_id'] ?? null) ? $payload['organization_id'] : null,
            deliveryId: is_string($data['delivery_id'] ?? null) ? $data['delivery_id'] : null,
            deliveredAt: is_numeric($ts = $request->header('X-Cbox-Timestamp')) ? (int) $ts : time(),
        );

        // Enqueue only when something will handle it — skip a no-op job otherwise. The
        // handlers run on a worker (see ProcessCboxIdWebhook), so the response returns
        // now regardless of how heavy they are.
        $queued = $this->handlers->hasHandlerFor($event->type);

        if ($queued) {
            $connection = config('cbox-id-client.webhooks.connection');
            $queue = config('cbox-id-client.webhooks.queue');

            ProcessCboxIdWebhook::dispatch($event)
                ->onConnection(is_string($connection) && $connection !== '' ? $connection : null)
                ->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
        }

        return new JsonResponse(['received' => true, 'queued' => $queued]);
    }
}
