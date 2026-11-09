<?php

declare(strict_types=1);

use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\Support\IssuedMachineToken;
use Cbox\Id\Client\Support\MachineTokenCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/*
 * Cbox ID throttles /oauth/token per source address, and apps behind one tunnel share
 * that address. A token per request from one app answered 429 to all of them, so the
 * client keeps machine tokens until shortly before they expire.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    Cache::flush();
});

/** Count the requests that reached the token endpoint. */
function tokenRequests(): int
{
    return Http::recorded(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/token'))->count();
}

/** An unsigned JWT-shaped token; the cache only reads its `exp`. */
function tokenExpiringIn(int $seconds, string $id = 'a'): string
{
    $b64 = static fn (string $part): string => rtrim(strtr(base64_encode($part), '+/', '-_'), '=');

    return $b64('{"alg":"none"}').'.'.$b64((string) json_encode(['exp' => time() + $seconds, 'jti' => $id])).'.sig';
}

/**
 * Serve a fresh token per request from the token endpoint, before the rest of the stub.
 *
 * @param  Closure(int): array<string, mixed>  $body
 */
function fakeTokenEndpoint(Closure $body): void
{
    $count = 0;
    Http::fake(['*/oauth/token' => static function () use (&$count, $body) {
        $count++;

        return Http::response($body($count));
    }]);
    fakeCbox();
}

function machineTokenKey(array $scopes, ?string $resource = null): string
{
    return (new MachineTokenCache(Cache::store(), Crypt::getFacadeRoot()))->key('https://id.test', 'client_1', $scopes, $resource);
}

it('fetches a machine token once and reuses it', function (): void {
    fakeCbox();
    $client = app(IdentityClient::class);

    $first = $client->machineToken(['api.read']);
    $second = $client->machineToken(['api.read']);

    expect($first)->toBe('at_1')
        ->and($second)->toBe('at_1')
        ->and(tokenRequests())->toBe(1);
});

it('shares one entry for the same scopes in any order, and keeps resources apart', function (): void {
    fakeCbox();
    $client = app(IdentityClient::class);

    $client->machineToken(['b', 'a']);
    $client->machineToken(['a', 'b', 'a']);
    expect(tokenRequests())->toBe(1);

    $client->machineToken(['a', 'b'], 'https://api.test');
    $client->machineToken(['c']);
    expect(tokenRequests())->toBe(3);
});

it('fetches again once the token is within a minute of its exp', function (): void {
    fakeTokenEndpoint(static fn (int $n): array => ['access_token' => tokenExpiringIn(120, "t{$n}"), 'expires_in' => 3600]);
    $client = app(IdentityClient::class);

    $first = $client->machineToken(['api.read']);
    expect($client->machineToken(['api.read']))->toBe($first);

    $this->travel(61)->seconds();

    expect($client->machineToken(['api.read']))->not->toBe($first)
        ->and(tokenRequests())->toBe(2);
});

it('falls back to expires_in for an opaque token', function (): void {
    fakeTokenEndpoint(static fn (int $n): array => ['access_token' => "opaque_{$n}", 'expires_in' => 120]);
    $client = app(IdentityClient::class);

    $client->machineToken(['api.read']);
    $this->travel(59)->seconds();
    expect($client->machineToken(['api.read']))->toBe('opaque_1');

    $this->travel(2)->seconds();
    expect($client->machineToken(['api.read']))->toBe('opaque_2');
});

it('does not cache a token that expires within the margin', function (): void {
    fakeTokenEndpoint(static fn (int $n): array => ['access_token' => tokenExpiringIn(30, "t{$n}")]);
    $client = app(IdentityClient::class);

    $client->machineToken(['api.read']);
    $client->machineToken(['api.read']);

    expect(tokenRequests())->toBe(2)
        ->and(Cache::get(machineTokenKey(['api.read'])))->toBeNull();
});

it('works out how long to keep a token', function (): void {
    $cache = new MachineTokenCache(new Repository(new ArrayStore), new Encrypter(str_repeat('k', 32), 'aes-256-cbc'));
    $now = 1_800_000_000;
    $jwt = static fn (int $exp): string => 'h.'.rtrim(strtr(base64_encode((string) json_encode(['exp' => $exp])), '+/', '-_'), '=').'.s';

    expect($cache->secondsToKeep(new IssuedMachineToken($jwt($now + 900), 60), $now))->toBe(840)
        ->and($cache->secondsToKeep(new IssuedMachineToken('opaque', 900), $now))->toBe(840)
        ->and($cache->secondsToKeep(new IssuedMachineToken('opaque'), $now))->toBe(240)
        ->and($cache->secondsToKeep(new IssuedMachineToken($jwt($now + 86_400)), $now))->toBe(3600)
        ->and($cache->secondsToKeep(new IssuedMachineToken('opaque', 86_400), $now))->toBe(3600)
        ->and($cache->secondsToKeep(new IssuedMachineToken($jwt($now + 30)), $now))->toBeLessThanOrEqual(0);
});

it('fetches under the cache lock and releases it afterwards', function (): void {
    $key = machineTokenKey(['api.read']);
    $heldDuringFetch = null;

    Http::fake(['*/oauth/token' => static function () use ($key, &$heldDuringFetch) {
        $probe = Cache::lock($key.'.lock', 10);
        $heldDuringFetch = ! $probe->get();

        if (! $heldDuringFetch) {
            $probe->release();
        }

        return Http::response(['access_token' => 'at_locked', 'expires_in' => 900]);
    }]);
    fakeCbox();

    expect(app(IdentityClient::class)->machineToken(['api.read']))->toBe('at_locked')
        ->and($heldDuringFetch)->toBeTrue();

    $after = Cache::lock($key.'.lock', 10);
    expect($after->get())->toBeTrue();
    $after->release();
});

it('fetches on its own when another worker holds the lock too long', function (): void {
    fakeCbox();
    $held = Cache::lock(machineTokenKey(['api.read']).'.lock', 30);
    expect($held->get())->toBeTrue();

    expect(app(IdentityClient::class)->machineToken(['api.read']))->toBe('at_1')
        ->and(tokenRequests())->toBe(1);

    $held->release();
});

it('stores the token encrypted with the app key', function (): void {
    fakeCbox();

    app(IdentityClient::class)->machineToken(['api.read']);
    $stored = Cache::get(machineTokenKey(['api.read']));

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('at_1')
        ->and(Crypt::decryptString($stored))->toBe('at_1');
});

it('forgets a corrupt entry and fetches again', function (mixed $corrupt): void {
    fakeCbox();
    $key = machineTokenKey(['api.read']);
    Cache::put($key, $corrupt, 600);

    expect(app(IdentityClient::class)->machineToken(['api.read']))->toBe('at_1')
        ->and(tokenRequests())->toBe(1)
        ->and(Crypt::decryptString(Cache::get($key)))->toBe('at_1');
})->with([
    'not ciphertext' => ['garbage'],
    'encrypted under another key' => [(new Encrypter(str_repeat('x', 32), 'aes-256-cbc'))->encryptString('at_other')],
    'not a string' => [['at_1']],
]);

it('fetches a fresh token after forgetMachineToken()', function (): void {
    fakeTokenEndpoint(static fn (int $n): array => ['access_token' => "opaque_{$n}", 'expires_in' => 900]);
    $client = app(IdentityClient::class);

    $client->machineToken(['b', 'a']);
    $client->forgetMachineToken(['a', 'b']);

    expect($client->machineToken(['a', 'b']))->toBe('opaque_2');
});

it('asks Cbox ID on every call when the cache is switched off', function (): void {
    config(['cbox-id-client.token_cache.enabled' => false]);
    app()->forgetInstance(IdentityClient::class);
    fakeCbox();
    $client = app(IdentityClient::class);

    $client->machineToken(['api.read']);
    $client->machineToken(['api.read']);
    $client->forgetMachineToken(['api.read']);

    expect(tokenRequests())->toBe(2)
        ->and(Cache::get(machineTokenKey(['api.read'])))->toBeNull();
});

it('uses the configured cache store', function (): void {
    config(['cache.stores.tokens' => ['driver' => 'array'], 'cbox-id-client.token_cache.store' => 'tokens']);
    app()->forgetInstance(IdentityClient::class);
    fakeCbox();

    app(IdentityClient::class)->machineToken(['api.read']);

    expect(Cache::store('tokens')->get(machineTokenKey(['api.read'])))->toBeString()
        ->and(Cache::get(machineTokenKey(['api.read'])))->toBeNull();
});

it('caches nothing when the client is built by hand without a cache', function (): void {
    fakeCbox();
    $client = new IdentityClient(
        ['issuer' => 'https://id.test', 'client_id' => 'client_1', 'client_secret' => 'secret_1'],
        new Discovery('https://id.test', 3600, 10),
    );

    $client->machineToken(['api.read']);
    $client->machineToken(['api.read']);
    $client->forgetMachineToken(['api.read']);

    expect(tokenRequests())->toBe(2);
});

it('caches in a store handed to a client built by hand', function (): void {
    fakeCbox();
    $store = new Repository(new ArrayStore);
    $client = new IdentityClient(
        ['issuer' => 'https://id.test', 'client_id' => 'client_1', 'client_secret' => 'secret_1'],
        new Discovery('https://id.test', 3600, 10),
        machineTokens: new MachineTokenCache($store, new Encrypter(str_repeat('s', 32), 'aes-256-cbc')),
    );

    $client->machineToken(['api.read']);
    $client->machineToken(['api.read']);

    expect(tokenRequests())->toBe(1);
});

it('still resolves the client, uncached, when the app has no key', function (): void {
    config(['app.key' => null]);
    app()->forgetInstance(IdentityClient::class);
    fakeCbox();
    $client = app(IdentityClient::class);

    $client->machineToken(['api.read']);
    $client->machineToken(['api.read']);

    expect(tokenRequests())->toBe(2);
});
