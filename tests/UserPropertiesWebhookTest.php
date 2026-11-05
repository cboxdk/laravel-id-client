<?php

declare(strict_types=1);

use Cbox\Id\Client\Events\UserPropertiesUpdated;
use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\Webhooks\ProcessCboxIdWebhook;
use Cbox\Id\Client\Webhooks\StandardWebhookSignature;
use Cbox\Id\Client\Webhooks\WebhookHandlers;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

function propertyEnvelope(string $namespace = 'preferences'): array
{
    return [
        'type' => $namespace === 'preferences' ? 'user.preferences.updated' : 'user.metadata.updated',
        'delivery_id' => 'delivery_1',
        'sequence' => 17,
        'data' => [
            'event_id' => 'event_1', 'user_id' => 'user_1', 'namespace' => $namespace,
            'changed_keys' => ['theme'], 'version' => 3, 'client_id' => 'client_1',
            'occurred_at' => '2026-11-05T10:00:00+00:00',
        ],
    ];
}

function sendPropertyWebhook(array $envelope, bool $standard = false, bool $forged = false): TestResponse
{
    $secret = 'test-secret';
    config(['cbox-id-client.webhooks.secret' => $secret]);
    $body = json_encode($envelope, JSON_THROW_ON_ERROR);
    $ts = time();
    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($standard) {
        $headers += [
            'HTTP_WEBHOOK_ID' => 'delivery_1',
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $ts,
            'HTTP_WEBHOOK_SIGNATURE' => $forged ? 'v1,invalid' : StandardWebhookSignature::sign('delivery_1', $ts, $body, StandardWebhookSignature::secretFor($secret)),
        ];
    } else {
        $headers['HTTP_X_CBOX_SIGNATURE'] = 't='.$ts.',v1='.($forged ? 'invalid' : hash_hmac('sha256', $ts.'.'.$body, $secret));
    }

    return test()->call('POST', '/cbox-id/webhooks', [], [], [], $headers, $body);
}

it('dispatches typed changes only when the verified queue job runs, preserving existing handlers', function (string $namespace, bool $standard): void {
    config(['cbox-id-client.issuer' => 'https://id.example.test/env/']);
    Queue::fake();
    Event::fake([UserPropertiesUpdated::class]);
    $custom = 0;
    CboxIdWebhooks::on('*', function () use (&$custom): void {
        $custom++;
    });

    sendPropertyWebhook(propertyEnvelope($namespace), $standard)->assertOk()->assertJson(['queued' => true]);
    Event::assertNotDispatched(UserPropertiesUpdated::class);
    Queue::assertPushed(ProcessCboxIdWebhook::class, function ($job) use ($namespace, &$custom): bool {
        $job->handle(app(WebhookHandlers::class));
        Event::assertDispatched(UserPropertiesUpdated::class, fn ($event): bool => $event->issuer === 'https://id.example.test/env'
            && $event->userId === 'user_1' && $event->eventId === 'event_1'
            && $event->namespace === $namespace && $event->version === 3
            && $event->changedKeys === ['theme'] && $event->clientId === 'client_1'
            && $event->occurredAt === '2026-11-05T10:00:00+00:00'
            && $event->webhook->deliveryId === 'delivery_1' && $event->webhook->sequence === 17);
        expect($custom)->toBe(1);

        return true;
    });
})->with([['preferences', false], ['planning', false], ['preferences', true], ['planning', true]]);

it('rejects forged property deliveries before queuing or dispatching', function (bool $standard): void {
    Queue::fake();
    Event::fake([UserPropertiesUpdated::class]);
    sendPropertyWebhook(propertyEnvelope(), $standard, true)->assertUnauthorized();
    Queue::assertNothingPushed();
    Event::assertNotDispatched(UserPropertiesUpdated::class);
})->with([false, true]);

it('rejects malformed property events without poisoning the queue', function (string $key, mixed $value): void {
    Queue::fake();
    $body = propertyEnvelope();
    $body['data'][$key] = $value;
    sendPropertyWebhook($body)->assertUnprocessable();
    Queue::assertNothingPushed();
})->with([
    ['version', '3'], ['version', 0], ['user_id', ''], ['event_id', null],
    ['namespace', 'planning'], ['changed_keys', ['theme' => true]], ['changed_keys', [13]],
    ['client_id', []],
]);

it('preserves retries and out-of-order notifications for consumer version reconciliation', function (): void {
    Event::fake([UserPropertiesUpdated::class]);
    $body = propertyEnvelope('planning');
    $body['data']['client_id'] = null;
    sendPropertyWebhook($body)->assertOk();
    sendPropertyWebhook($body)->assertOk();
    $body['data']['version'] = 2;
    sendPropertyWebhook($body)->assertOk();
    Event::assertDispatchedTimes(UserPropertiesUpdated::class, 3);
});

it('lets listener failures fail the queue job so the worker can retry', function (): void {
    Queue::fake();
    Event::listen(UserPropertiesUpdated::class, function (): void {
        throw new RuntimeException('retry me');
    });
    sendPropertyWebhook(propertyEnvelope())->assertOk();
    Queue::assertPushed(ProcessCboxIdWebhook::class, function ($job): bool {
        expect(fn () => $job->handle(app(WebhookHandlers::class)))->toThrow(RuntimeException::class, 'retry me');

        return true;
    });
});
