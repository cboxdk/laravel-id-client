<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\Tenancy\PermissionGate;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * Customer API keys, verified against `POST /oauth/api-keys/verify` as this app.
 */
beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'cid_app',
        'cbox-id-client.client_secret' => 'csec_app',
        'cbox-id-client.api_keys.cache_ttl' => 60,
    ]);
    Cache::flush();
});

/** @param array<string, mixed> $overrides */
function liveKey(array $overrides = []): array
{
    return array_replace([
        'active' => true,
        'key_id' => 'key_1',
        'sub' => 'user_1',
        'org' => 'org_1',
        'org_role' => 'admin',
        'permissions' => ['reports:read'],
        'client_id' => 'cid_app',
        'expires_at' => null,
    ], $overrides);
}

function verifyCount(): int
{
    return Http::recorded(fn (HttpRequest $r): bool => str_ends_with($r->url(), '/oauth/api-keys/verify'))->count();
}

it('asks Cbox ID with the app credentials and returns the key holder', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey())]);

    $key = CboxId::verifyApiKey('ctx_live_abc');

    expect($key->keyId)->toBe('key_1')
        ->and($key->subjectId())->toBe('user_1')
        ->and($key->organization()?->id)->toBe('org_1')
        ->and($key->organization()?->role)->toBe(OrganizationRole::Admin)
        ->and($key->hasPermission('reports:read'))->toBeTrue()
        ->and($key->isSupportSession())->toBeFalse();

    Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://id.test/oauth/api-keys/verify'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('cid_app:csec_app'))
        && $r['key'] === 'ctx_live_abc');
});

it('caches a live answer briefly, keyed on a hash rather than the key', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey())]);

    CboxId::verifyApiKey('ctx_live_abc');
    CboxId::verifyApiKey('ctx_live_abc');

    expect(verifyCount())->toBe(1);

    $keys = array_keys((fn () => $this->storage)->call(Cache::store()->getStore()));
    expect(implode(' ', $keys))->not->toContain('ctx_live_abc');
});

it('never caches a no', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response(['active' => false])]);

    foreach ([1, 2] as $_) {
        try {
            CboxId::verifyApiKey('ctx_live_nope');
        } catch (ApiKeyRejected) {
        }
    }

    expect(verifyCount())->toBe(2)
        // Nothing written at all: random keys must not be able to fill the cache.
        ->and((fn () => $this->storage)->call(Cache::store()->getStore()))->toBe([]);
});

it('asks every time when the cache is turned off', function (): void {
    config(['cbox-id-client.api_keys.cache_ttl' => 0]);
    $this->app->forgetInstance(VerifiesApiKeys::class);
    Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey())]);

    CboxId::verifyApiKey('ctx_live_abc');
    CboxId::verifyApiKey('ctx_live_abc');

    expect(verifyCount())->toBe(2);
});

it('does not cache a key past its own expiry', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey(['expires_at' => time() + 1]))]);

    CboxId::verifyApiKey('ctx_live_abc');
    $this->travel(2)->seconds();

    expect(fn () => CboxId::verifyApiKey('ctx_live_abc'))->toThrow(ApiKeyRejected::class);
});

it('will not serve a cached answer past the key\'s expiry, whatever the store kept', function (): void {
    // A store that rounds TTLs up (or a clock that drifted) must not stretch a key.
    $verifier = app(VerifiesApiKeys::class);
    $cacheKey = (fn (string $key): string => $this->cacheKey($key))->call($verifier, 'ctx_live_abc');
    Cache::put($cacheKey, liveKey(['expires_at' => time() - 10]), 600);
    Http::fake(['*/oauth/api-keys/verify' => Http::response(['active' => false])]);

    expect(fn () => CboxId::verifyApiKey('ctx_live_abc'))->toThrow(ApiKeyRejected::class, 'The API key is not active.');
});

it('refuses an answer about a key for another application', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey(['client_id' => 'cid_other']))]);

    expect(fn () => CboxId::verifyApiKey('ctx_live_abc'))->toThrow(ApiKeyRejected::class, 'belongs to another application');
});

it('refuses an inactive key and a live key without the permission, differently', function (): void {
    Http::fake([
        '*/oauth/api-keys/verify' => Http::sequence()
            ->push(['active' => false])
            ->push(liveKey()),
    ]);

    try {
        CboxId::verifyApiKey('ctx_live_dead');
        $this->fail('Expected a rejection.');
    } catch (ApiKeyRejected $e) {
        expect($e->isInsufficientPermission())->toBeFalse();
    }

    try {
        CboxId::verifyApiKey('ctx_live_abc', ['reports:write']);
        $this->fail('Expected a rejection.');
    } catch (ApiKeyRejected $e) {
        expect($e->isInsufficientPermission())->toBeTrue()->and($e->missingPermissions)->toBe(['reports:write']);
    }
});

it('never reads an outage as a bad key', function (): void {
    Http::fake(['*/oauth/api-keys/verify' => Http::response('upstream down', 502)]);

    expect(fn () => CboxId::verifyApiKey('ctx_live_abc'))->toThrow(ApiKeyVerificationUnavailable::class, 'HTTP 502');
});

describe('the cbox-id.api-key middleware', function (): void {
    beforeEach(function (): void {
        Route::middleware('cbox-id.api-key')->get('/v1/whoami', fn (VerifiedApiKey $key) => ['sub' => $key->subject, 'org' => $key->organizationId]);
        Route::middleware('cbox-id.api-key:reports:read')->get('/v1/reports', fn () => ['ok' => true]);
        Route::middleware(['cbox-id.api-key', 'cbox-id.org:admin', 'cbox-id.permission:reports:read'])->get('/v1/admin-reports', fn () => ['ok' => true]);
    });

    it('lets a live key through and makes it the request principal', function (): void {
        Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey())]);

        $this->getJson('/v1/whoami', ['Authorization' => 'Bearer ctx_live_abc'])
            ->assertOk()->assertJson(['sub' => 'user_1', 'org' => 'org_1']);
        $this->getJson('/v1/admin-reports', ['Authorization' => 'Bearer ctx_live_abc'])->assertOk();
    });

    it('answers 401 with no key, 401 for a dead key, 403 for a missing permission', function (): void {
        Http::fake(['*/oauth/api-keys/verify' => Http::sequence()->push(['active' => false])->push(liveKey(['permissions' => []]))]);

        $this->getJson('/v1/reports')->assertUnauthorized()->assertJsonPath('error.type', 'invalid_request');
        $this->getJson('/v1/reports', ['Authorization' => 'Bearer ctx_live_dead'])->assertUnauthorized()->assertJsonPath('error.type', 'invalid_token');
        $this->getJson('/v1/reports', ['Authorization' => 'Bearer ctx_live_abc'])->assertForbidden()->assertJsonPath('error.type', 'insufficient_permission');
    });

    it('answers 503 with Retry-After when Cbox ID cannot be asked', function (): void {
        Http::fake(['*/oauth/api-keys/verify' => Http::response('', 503)]);

        $this->getJson('/v1/reports', ['Authorization' => 'Bearer ctx_live_abc'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '5')
            ->assertJsonPath('error.type', 'temporarily_unavailable');
    });

    it('feeds the permission gate like any other principal', function (): void {
        PermissionGate::register(app(Illuminate\Contracts\Auth\Access\Gate::class), app(CurrentPrincipal::class));
        Route::middleware('cbox-id.api-key')->get('/v1/gate', fn () => ['read' => Gate::allows('reports:read'), 'write' => Gate::allows('reports:write')]);
        Http::fake(['*/oauth/api-keys/verify' => Http::response(liveKey())]);

        $this->getJson('/v1/gate', ['Authorization' => 'Bearer ctx_live_abc'])->assertExactJson(['read' => true, 'write' => false]);
    });
});
