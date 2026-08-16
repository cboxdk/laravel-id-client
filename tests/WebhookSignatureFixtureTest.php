<?php

declare(strict_types=1);

use Cbox\Id\Client\IdentityClient;

/**
 * Golden webhook-signature vectors, shared byte-for-byte with laravel-id (the
 * sender) and with id-js, id-python and id-go.
 *
 * WebhookReceiverTest signs with its own copy of the formula and then verifies it,
 * so it stays green even when this package and the server disagree: flip the signed
 * string from `{timestamp}.{body}` to `{body}.{timestamp}` on either side and that
 * suite still passes while every delivery 401s in the field. The signatures below
 * are fixed bytes produced by the server implementation and independently reproduced
 * with OpenSSL and Python.
 *
 * @return array<string, array{0: array<string, mixed>}>
 */
function webhookSignatureFixtureCases(): array
{
    $dataset = [];
    foreach (webhookSignatureFixture()['cases'] as $case) {
        $dataset[$case['name']] = [$case];
    }

    return $dataset;
}

/**
 * @return array{signed_payload_template: string, header_template: string, cases: list<array<string, mixed>>}
 */
function webhookSignatureFixture(): array
{
    /** @var array{signed_payload_template: string, header_template: string, cases: list<array<string, mixed>>} */
    return json_decode(
        (string) file_get_contents(__DIR__.'/Fixtures/webhook_signature.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

/**
 * The named case from the shared fixture.
 *
 * @return array<string, mixed>
 */
function webhookSignatureCase(string $name): array
{
    foreach (webhookSignatureFixture()['cases'] as $case) {
        if ($case['name'] === $name) {
            return $case;
        }
    }

    throw new RuntimeException("The shared fixture has no case named [{$name}].");
}

/**
 * The fixture timestamps are deliberately pinned in the past; widen the freshness
 * window enough to reach them. Freshness itself is covered by WebhookReceiverTest.
 */
function toleranceReaching(int $timestamp): int
{
    return abs(time() - $timestamp) + 300;
}

it('accepts the golden signature from the shared cross-SDK fixture', function (array $case): void {
    $verified = app(IdentityClient::class)->verifyWebhook(
        $case['body'],
        $case['header'],
        $case['secret'],
        toleranceReaching((int) $case['timestamp']),
    );

    expect($verified)->toBeTrue();
})->with(webhookSignatureFixtureCases());

it('rejects a signature made over the reversed concatenation', function (array $case): void {
    // The same secret, timestamp and body signed as `{body}.{timestamp}`. A verifier
    // that concatenates the other way round accepts this — and rejects every real
    // delivery Cbox ID sends.
    $verified = app(IdentityClient::class)->verifyWebhook(
        $case['body'],
        $case['reversed_order_header'],
        $case['secret'],
        toleranceReaching((int) $case['timestamp']),
    );

    expect($verified)->toBeFalse();
})->with(webhookSignatureFixtureCases());

it('rejects a golden signature replayed against a tampered body', function (array $case): void {
    $verified = app(IdentityClient::class)->verifyWebhook(
        $case['body'].' ',
        $case['header'],
        $case['secret'],
        toleranceReaching((int) $case['timestamp']),
    );

    expect($verified)->toBeFalse();
})->with(webhookSignatureFixtureCases());

it('verifies the raw request bytes, not a re-serialized copy of the parsed body', function (): void {
    // The unicode case ships escaped slashes and \uXXXX escapes. Re-encoding the
    // decoded payload yields equivalent JSON with different bytes, which must NOT
    // verify — which is exactly why WebhookController signs $request->getContent()
    // and never a re-encoded array.
    $case = webhookSignatureCase('unicode_and_escaped_slashes');

    $reSerialized = (string) json_encode(json_decode((string) $case['body'], true, 512, JSON_THROW_ON_ERROR), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    expect($reSerialized)->not->toBe($case['body']);

    expect(app(IdentityClient::class)->verifyWebhook(
        $reSerialized,
        $case['header'],
        $case['secret'],
        toleranceReaching((int) $case['timestamp']),
    ))->toBeFalse();
});

it('delivers a fixture-shaped payload through the receiver end to end', function (): void {
    // Same envelope the fixture pins, signed with the fixture's own secret but at a
    // current timestamp so the receiver's freshness check (which has no injectable
    // clock) is satisfied. Proves the whole route — signature, decode, dispatch —
    // agrees with the shared wire format, not just the verifier in isolation.
    $fixture = webhookSignatureFixture();
    $case = webhookSignatureCase('envelope');

    config(['cbox-id-client.webhooks.secret' => $case['secret']]);

    // The concatenation ORDER comes from the shared fixture's template, never from a
    // local re-implementation of the formula; only the clock is local, because the
    // receiver's freshness check has no injectable now().
    $timestamp = (string) time();
    $signedPayload = strtr($fixture['signed_payload_template'], [
        '{timestamp}' => $timestamp,
        '{body}' => (string) $case['body'],
    ]);
    $header = strtr($fixture['header_template'], [
        '{timestamp}' => $timestamp,
        '{signature}' => hash_hmac('sha256', $signedPayload, (string) $case['secret']),
    ]);

    $this->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => $header,
        'HTTP_X_CBOX_TIMESTAMP' => $timestamp,
        'CONTENT_TYPE' => 'application/json',
    ], (string) $case['body'])->assertOk()->assertJson(['received' => true]);
});

/**
 * The wire format, stated once as a constant.
 *
 * This package verifies against its OWN copy of the fixture, as each SDK does, so a copy
 * that drifts is silent: this suite stays green against the drifted bytes while every
 * delivery from the server 401s in the field. The docblock at the top calls the copies
 * "shared byte-for-byte" and nothing enforced it — the templates were the one field no
 * test read.
 *
 * Deliberately NOT derived from the file it guards. `{timestamp}.{body}` is the contract
 * with the sender; if a copy here says otherwise, this package is wrong and should say so
 * loudly rather than agree with itself.
 */
it('pins the signed-payload order this package must agree with the server on', function (): void {
    $document = webhookSignatureFixture();

    expect($document['signed_payload_template'])->toBe('{timestamp}.{body}')
        ->and($document['header_template'])->toBe('t={timestamp},v1={signature}');
});

it('builds each case literal from the templates it publishes', function (array $case): void {
    $document = webhookSignatureFixture();

    $signedPayload = strtr($document['signed_payload_template'], [
        '{timestamp}' => (string) $case['timestamp'],
        '{body}' => $case['body'],
    ]);

    expect($signedPayload)->toBe($case['signed_payload'], 'the signed-payload template disagrees with the case it published');
})->with(webhookSignatureFixtureCases());
