<?php

declare(strict_types=1);

use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['cbox-id-client.webhooks.secret' => 'whsec_test']);
});

function deliver(string $body, string $secret = 'whsec_test', ?int $ts = null): TestResponse
{
    $ts ??= time();
    $signature = hash_hmac('sha256', $ts.'.'.$body, $secret);

    return test()->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => "t={$ts},v1={$signature}",
        'HTTP_X_CBOX_TIMESTAMP' => (string) $ts,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('dispatches a verified webhook to the registered handler', function (): void {
    $received = null;
    CboxIdWebhooks::on('role.assigned', function (WebhookEvent $event) use (&$received): void {
        $received = $event;
    });

    $body = (string) json_encode([
        'type' => 'role.assigned',
        'data' => ['user_id' => 'user_1', 'role_id' => 'role_1', 'organization_id' => 'org_1'],
        'delivery_id' => 'wd_123',
    ]);

    deliver($body)->assertOk()->assertJson(['received' => true, 'handled' => 1]);

    expect($received)->toBeInstanceOf(WebhookEvent::class)
        ->and($received->type)->toBe('role.assigned')
        ->and($received->string('user_id'))->toBe('user_1')
        ->and($received->organizationId)->toBe('org_1')
        ->and($received->deliveryId)->toBe('wd_123');
});

it('also runs wildcard handlers', function (): void {
    $runs = 0;
    CboxIdWebhooks::on('*', function () use (&$runs): void {
        $runs++;
    });

    deliver((string) json_encode(['type' => 'organization.member_added', 'data' => []]))->assertOk();

    expect($runs)->toBe(1);
});

it('rejects a forged signature', function (): void {
    $body = (string) json_encode(['type' => 'role.assigned', 'data' => []]);

    test()->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => 't=1,v1=deadbeef',
    ], $body)->assertStatus(401);
});

it('rejects a replayed (stale-timestamp) signature', function (): void {
    $body = (string) json_encode(['type' => 'role.assigned', 'data' => []]);

    deliver($body, ts: time() - 100000)->assertStatus(401);
});

it('422s a body with no event type', function (): void {
    deliver((string) json_encode(['data' => ['x' => 1]]))->assertStatus(422);
});

it('fails closed when no signing secret is configured', function (): void {
    config(['cbox-id-client.webhooks.secret' => null]);

    deliver((string) json_encode(['type' => 'role.assigned', 'data' => []]))->assertStatus(500);
});

it('does not register the route when disabled', function (): void {
    expect(app('router')->getRoutes()->hasNamedRoute('cbox-id.webhooks'))->toBeTrue();
});
