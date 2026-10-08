<?php

declare(strict_types=1);

use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\Webhooks\StandardWebhookSignature;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Illuminate\Testing\TestResponse;

/*
 * Standard Webhooks (https://www.standardwebhooks.com/), the signature scheme an endpoint
 * gets with `signature_scheme: standard_webhooks`. Proven against the specification's own
 * published vector — the one its reference libraries test `sign` with.
 */

const SW_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
const SW_ID = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
const SW_TIMESTAMP = 1614265330;
const SW_BODY = '{"test": 2432232314}';
const SW_SIGNATURE = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

function swHeaders(string $signature = SW_SIGNATURE, string|int $timestamp = SW_TIMESTAMP): array
{
    return ['webhook-id' => SW_ID, 'webhook-timestamp' => (string) $timestamp, 'webhook-signature' => $signature];
}

it('signs the specification vector', function (): void {
    expect(StandardWebhookSignature::sign(SW_ID, SW_TIMESTAMP, SW_BODY, SW_SECRET))->toBe(SW_SIGNATURE);
});

it('verifies the specification vector', function (): void {
    expect(StandardWebhookSignature::verify(SW_BODY, swHeaders(), SW_SECRET, now: SW_TIMESTAMP))->toBeTrue()
        // Laravel's $request->headers->all() shape, any case.
        ->and(StandardWebhookSignature::verify(SW_BODY, ['Webhook-Id' => [SW_ID], 'WEBHOOK-TIMESTAMP' => [(string) SW_TIMESTAMP], 'webhook-signature' => [SW_SIGNATURE]], SW_SECRET, now: SW_TIMESTAMP))->toBeTrue()
        // One of several entries — a sender signing with two secrets mid-rotation.
        ->and(StandardWebhookSignature::verify(SW_BODY, swHeaders('v1,bm90LXRoaXMtb25l v1a,ZWQyNTUxOQ== '.SW_SIGNATURE), SW_SECRET, now: SW_TIMESTAMP))->toBeTrue();
});

it('refuses anything else', function (array $headers, string $body, string $secret, int $now): void {
    expect(StandardWebhookSignature::verify($body, $headers, $secret, now: $now))->toBeFalse();
})->with([
    'a changed body' => [swHeaders(), '{"test": 2432232315}', SW_SECRET, SW_TIMESTAMP],
    'another secret' => [swHeaders(), SW_BODY, 'whsec_'.base64_encode('another secret entirely!'), SW_TIMESTAMP],
    'too old' => [swHeaders(), SW_BODY, SW_SECRET, SW_TIMESTAMP + 301],
    'from the future' => [swHeaders(), SW_BODY, SW_SECRET, SW_TIMESTAMP - 301],
    'only another version' => [swHeaders('v1a,'.substr(SW_SIGNATURE, 3)), SW_BODY, SW_SECRET, SW_TIMESTAMP],
    'a malformed signature header' => [swHeaders('nonsense'), SW_BODY, SW_SECRET, SW_TIMESTAMP],
    'a missing id' => [array_diff_key(swHeaders(), ['webhook-id' => true]), SW_BODY, SW_SECRET, SW_TIMESTAMP],
    'a non-numeric timestamp' => [swHeaders(timestamp: '1614265330.5'), SW_BODY, SW_SECRET, SW_TIMESTAMP],
    'a secret without whsec_' => [swHeaders(), SW_BODY, substr(SW_SECRET, 6), SW_TIMESTAMP],
    'a secret that is not base64' => [swHeaders(), SW_BODY, 'whsec_***', SW_TIMESTAMP],
]);

it('converts a Cbox-scheme secret the way Cbox ID does when an endpoint changes scheme', function (): void {
    $hex = str_repeat('ab', 32);

    expect(StandardWebhookSignature::secretFor($hex))->toBe('whsec_'.base64_encode($hex))
        ->and(StandardWebhookSignature::secretFor(SW_SECRET))->toBe(SW_SECRET);

    // The converted secret's key is the hex string's bytes — the Cbox scheme's key.
    $signature = StandardWebhookSignature::sign(SW_ID, SW_TIMESTAMP, SW_BODY, StandardWebhookSignature::secretFor($hex));
    expect($signature)->toBe('v1,'.base64_encode(hash_hmac('sha256', SW_ID.'.'.SW_TIMESTAMP.'.'.SW_BODY, $hex, true)));
});

it('is on the facade, converting the configured secret', function (): void {
    $now = time();
    $hex = str_repeat('cd', 32);
    $signature = (string) StandardWebhookSignature::sign(SW_ID, $now, SW_BODY, StandardWebhookSignature::secretFor($hex));

    expect(CboxId::verifyStandardWebhook(SW_BODY, swHeaders($signature, $now), $hex))->toBeTrue()
        ->and(CboxId::verifyStandardWebhook(SW_BODY, swHeaders($signature, $now), 'whsec_'.base64_encode($hex)))->toBeTrue();
});

function deliverStandard(string $body, string $secret, ?int $ts = null): TestResponse
{
    $ts ??= time();

    return test()->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_WEBHOOK_ID' => 'wd_std_1',
        'HTTP_WEBHOOK_TIMESTAMP' => (string) $ts,
        'HTTP_WEBHOOK_SIGNATURE' => (string) StandardWebhookSignature::sign('wd_std_1', $ts, $body, $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('receives a Standard Webhooks delivery with the configured secret, hex or whsec_', function (string $configured, string $signedWith): void {
    config(['cbox-id-client.webhooks.secret' => $configured]);
    $received = null;
    CboxIdWebhooks::on('role.assigned', function (WebhookEvent $event) use (&$received): void {
        $received = $event;
    });

    $body = (string) json_encode(['type' => 'role.assigned', 'data' => ['user_id' => 'user_1'], 'delivery_id' => 'wd_std_1']);

    deliverStandard($body, $signedWith, 1_700_000_000)->assertStatus(401); // stale
    deliverStandard($body, $signedWith)->assertOk()->assertJson(['received' => true, 'queued' => true]);
    deliverStandard($body, 'whsec_'.base64_encode('the wrong key'))->assertStatus(401);

    expect($received?->deliveryId)->toBe('wd_std_1')->and($received?->string('user_id'))->toBe('user_1');
})->with([
    'a hex secret, converted' => [str_repeat('ef', 32), 'whsec_'.base64_encode(str_repeat('ef', 32))],
    'a whsec_ secret' => [SW_SECRET, SW_SECRET],
]);
