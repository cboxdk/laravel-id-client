<?php

declare(strict_types=1);

use Cbox\Id\Client\IdentityClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

use function Illuminate\Support\defer;

/*
 * A request finds a valid machine token. Tokens in use are renewed before they expire by
 * `cbox-id:refresh` (which the package schedules itself), and by the request that
 * notices one is about to expire, after its response. A request asks Cbox ID only when
 * there is no token at all.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    // Cbox ID's lifetime: 900 seconds, kept for 840.
    $this->tokenEndpointBroken = false;
    $broken = &$this->tokenEndpointBroken;
    fakeTokenEndpoint(static function (int $n) use (&$broken): array {
        return $broken ? ['error' => 'server_error'] : ['access_token' => "opaque_{$n}", 'expires_in' => 900];
    });
});

it('renews tokens in use ahead of expiry, so the next request needs no call', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $client->machineToken(['app_catalog:resolve']);

    // Five minutes on, each has nine minutes left: inside the ten-minute window.
    $this->travel(300)->seconds();

    expect($client->refreshMachineTokens())->toBe(2)
        ->and(tokenRequests())->toBe(4);

    // A request later than the first tokens would have lived.
    $this->travel(600)->seconds();

    expect($client->machineToken(['decisions:read']))->toBe('opaque_3')
        ->and($client->machineToken(['app_catalog:resolve']))->toBe('opaque_4')
        ->and(tokenRequests())->toBe(4);
});

it('leaves a token with more than the window left alone', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(200)->seconds();

    expect($client->refreshMachineTokens())->toBe(0)
        ->and(tokenRequests())->toBe(1);
});

it('fetches a token that expired while nothing renewed it, when it is still in use', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(2)->hours();

    expect($client->refreshMachineTokens())->toBe(1)
        ->and($client->machineToken(['decisions:read']))->toBe('opaque_2')
        ->and(tokenRequests())->toBe(2);
});

it('stops renewing a token nothing has used for a week', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);

    $this->travel(6)->days();
    expect($client->refreshMachineTokens())->toBe(1);

    $this->travel(2)->days();
    expect($client->refreshMachineTokens())->toBe(0)
        ->and(tokenRequests())->toBe(2);
});

it('keeps renewing a token that requests keep using', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);

    foreach (range(1, 9) as $day) {
        $this->travel(1)->days();
        $client->refreshMachineTokens();
        $client->machineToken(['decisions:read']);
    }

    // One call per renewal, none from a request.
    expect(tokenRequests())->toBe(10);
});

it('keeps renewed tokens apart by scope set and resource', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['b', 'a']);
    $client->machineToken(['a', 'b'], 'https://api.test');
    $this->travel(400)->seconds();

    expect($client->refreshMachineTokens())->toBe(2);

    $sent = Http::recorded()->map(static fn (array $pair): array => $pair[0]->data())->filter(static fn (array $data): bool => ($data['grant_type'] ?? null) === 'client_credentials')->values();

    expect($sent[2]['scope'])->toBe('a b')
        ->and($sent[3]['resource'] ?? $sent[2]['resource'] ?? null)->toBe('https://api.test');
});

it('stores a renewed token encrypted', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(400)->seconds();
    $client->refreshMachineTokens();

    $stored = Cache::get(machineTokenKey(['decisions:read']));

    expect($stored)->toBeString()->not->toContain('opaque_2')
        ->and(Crypt::decryptString($stored))->toBe('opaque_2');
});

it('renews after the response when a request finds a token about to expire', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(730)->seconds();

    // The request: the kept token, which is still good for two minutes, and no call.
    expect($client->machineToken(['decisions:read']))->toBe('opaque_1')
        ->and(tokenRequests())->toBe(1);

    defer()->invoke();

    expect(tokenRequests())->toBe(2)
        ->and($client->machineToken(['decisions:read']))->toBe('opaque_2');
});

it('does not renew after the response while the token has more than two minutes left', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(600)->seconds();
    $client->machineToken(['decisions:read']);
    defer()->invoke();

    expect(tokenRequests())->toBe(1);
});

it('does not renew a token another worker is renewing', function (): void {
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(400)->seconds();
    $held = Cache::lock(machineTokenKey(['decisions:read']).'.lock', 30);
    expect($held->get())->toBeTrue();

    expect($client->refreshMachineTokens())->toBe(0)
        ->and(tokenRequests())->toBe(1);

    $held->release();
});

it('keeps the token it has when a renewal fails, and reports it', function (): void {
    Exceptions::fake();
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);
    $this->travel(400)->seconds();
    $this->tokenEndpointBroken = true;

    expect($client->refreshMachineTokens())->toBe(0)
        ->and($client->machineToken(['decisions:read']))->toBe('opaque_1');
    Exceptions::assertReportedCount(1);
});

it('renews nothing without a token cache', function (): void {
    config(['cbox-id-client.token_cache.enabled' => false]);
    app()->forgetInstance(IdentityClient::class);
    $client = app(IdentityClient::class);
    $client->machineToken(['decisions:read']);

    expect($client->refreshMachineTokens())->toBe(0);
});

it('refreshes discovery and tokens from the command', function (): void {
    app(IdentityClient::class)->machineToken(['decisions:read']);
    $this->travel(3700)->seconds();

    $this->artisan('cbox-id:refresh')->assertSuccessful();

    $before = Http::recorded()->count();
    app(IdentityClient::class)->redirect();

    expect(app(IdentityClient::class)->machineToken(['decisions:read']))->toBe('opaque_2')
        ->and(Http::recorded()->count())->toBe($before);
});

it('does nothing, successfully, in an app with no issuer', function (): void {
    config(['cbox-id-client.issuer' => '']);
    app()->forgetInstance(IdentityClient::class);

    $this->artisan('cbox-id:refresh')->assertSuccessful();

    expect(Http::recorded()->count())->toBe(0);
});

it('schedules the command every five minutes by default', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(static fn ($event): bool => str_contains((string) $event->command, 'cbox-id:refresh'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('*/5 * * * *');
});

it('schedules nothing when switched off', function (): void {
    $this->configBeforeBoot = ['cbox-id-client.background.schedule' => false];
    $this->refreshApplication();

    $events = collect(app(Schedule::class)->events())->filter(static fn ($event): bool => str_contains((string) $event->command, 'cbox-id:refresh'));

    expect($events)->toHaveCount(0);
});
