<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\Exceptions\UserPropertiesFailed;
use Cbox\Id\Client\Testing\FakeUserProperties;
use Cbox\Id\Client\UserProperties\KeptUserProperties;
use Cbox\Id\Client\UserProperties\PropertyDocument;
use Cbox\Id\Client\UserProperties\PropertyToken;
use Cbox\Id\Client\UserProperties\RefreshKeptUserProperties;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

use function Illuminate\Support\defer;

/*
 * A person's shared properties (preferences) are read from the copy the app keeps.
 * Cbox ID is asked in the request only for a person nothing is kept for; after that the
 * copy is refreshed in the background, and at once when Cbox ID's webhook says it changed.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.webhooks.secret' => 'whsec_test',
    ]);
    Cache::flush();
    fakeCbox();
});

function preferences(int $version, string $language = 'da'): PropertyDocument
{
    return new PropertyDocument('user-1', 'preferences', (object) ['language' => $language, 'nested' => (object) [], 'list' => []], $version);
}

/** @param list<PropertyDocument|UserPropertiesFailed> $answers */
function fakeProperties(array $answers): FakeUserProperties
{
    $fake = new FakeUserProperties($answers);
    app()->instance(UserProperties::class, $fake);

    return $fake;
}

it('asks Cbox ID for a person nothing is kept for, then reads the kept copy', function (): void {
    $fake = fakeProperties([preferences(3)]);
    $kept = app(KeptUserProperties::class);

    $first = $kept->read('user-1', 'preferences');
    $second = $kept->read('user-1', 'preferences');

    expect($first->version)->toBe(3)
        ->and($second->data->language)->toBe('da')
        // Objects and lists come back as they were, not as arrays.
        ->and($second->data->nested)->toBeInstanceOf(stdClass::class)
        ->and($second->data->list)->toBe([])
        ->and($fake->calls)->toHaveCount(1);
});

it('serves a copy that has gone stale and queues a refresh', function (): void {
    Queue::fake();
    $fake = fakeProperties([preferences(3), preferences(4, 'en')]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');
    $this->travel(10)->days();

    expect($kept->read('user-1', 'preferences')->version)->toBe(3)
        ->and($fake->calls)->toHaveCount(1);
    Queue::assertPushed(RefreshKeptUserProperties::class, static fn (RefreshKeptUserProperties $job): bool => $job->user === 'user-1' && $job->namespace === 'preferences');
});

it('refreshes after the response in an app with no queue worker', function (): void {
    $fake = fakeProperties([preferences(3), preferences(4, 'en')]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');
    $this->travel(301)->seconds();

    expect($kept->read('user-1', 'preferences')->version)->toBe(3);
    defer()->invoke();

    expect($kept->read('user-1', 'preferences')->data->language)->toBe('en')
        ->and($fake->calls)->toHaveCount(2);
});

it('asks again in the request once the copy is older than thirty days', function (): void {
    fakeProperties([preferences(3), preferences(4)]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');
    $this->travel(31)->days();

    expect($kept->read('user-1', 'preferences')->version)->toBe(4);
});

it('uses the caller\'s token for the first read when given one', function (): void {
    fakeProperties([preferences(3)]);

    expect(app(KeptUserProperties::class)->read('user-1', 'preferences', new PropertyToken('at_user'))->version)->toBe(3)
        // No machine token was needed.
        ->and(tokenRequests())->toBe(0);
});

it('never replaces a kept copy with an older version', function (): void {
    fakeProperties([preferences(5), preferences(4, 'en')]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');

    $kept->refresh('user-1', 'preferences');

    expect($kept->read('user-1', 'preferences')->version)->toBe(5);
});

it('drops the kept copy when Cbox ID takes the access away, and keeps it through an outage', function (?int $status, bool $keeps): void {
    fakeProperties([preferences(3), new UserPropertiesFailed($status, 'x'), preferences(9)]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');

    (new RefreshKeptUserProperties('user-1', 'preferences'))->handle($kept);

    expect($kept->read('user-1', 'preferences')->version)->toBe($keeps ? 3 : 9);
})->with([
    'unauthorized' => [401, false],
    'forbidden' => [403, false],
    'gone' => [404, false],
    'server error' => [500, true],
    'unreachable' => [null, true],
]);

it('refreshes the kept copy when the webhook says the document changed', function (): void {
    $fake = fakeProperties([preferences(3), preferences(4, 'en')]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');

    $body = (string) json_encode(['type' => 'user.preferences.updated', 'delivery_id' => 'wd_1', 'data' => [
        'event_id' => 'evt_1', 'user_id' => 'user-1', 'namespace' => 'preferences', 'occurred_at' => '2026-11-12T10:00:00Z',
        'version' => 4, 'changed_keys' => ['language'], 'client_id' => null,
    ]]);
    $ts = time();
    $this->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => "t={$ts},v1=".hash_hmac('sha256', $ts.'.'.$body, 'whsec_test'),
        'HTTP_X_CBOX_TIMESTAMP' => (string) $ts, 'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($kept->read('user-1', 'preferences')->data->language)->toBe('en')
        ->and($fake->calls)->toHaveCount(2);
});

it('ignores a webhook for a document it does not keep, or already has', function (): void {
    $fake = fakeProperties([preferences(4)]);
    $kept = app(KeptUserProperties::class);

    $kept->changed('user-2', 'preferences', 9);
    $kept->read('user-1', 'preferences');
    $kept->changed('user-1', 'preferences', 4);

    expect($fake->calls)->toHaveCount(1);
});

it('keeps what a write returned', function (): void {
    $fake = fakeProperties([preferences(3)]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');

    $kept->remember(preferences(4, 'en'));

    expect($kept->read('user-1', 'preferences')->data->language)->toBe('en')
        ->and($fake->calls)->toHaveCount(1);
});

it('forgets on request', function (): void {
    $fake = fakeProperties([preferences(3), preferences(3)]);
    $kept = app(KeptUserProperties::class);
    $kept->read('user-1', 'preferences');
    $kept->forget('user-1', 'preferences');
    $kept->read('user-1', 'preferences');

    expect($fake->calls)->toHaveCount(2);
});
