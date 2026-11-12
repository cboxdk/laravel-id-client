<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Discovery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

use function Illuminate\Support\defer;

/*
 * The discovery document and the JWKS are fetched in a request once. After that a
 * request reads the kept copy however old it is (within `cache_keep`), and a copy older
 * than `cache_ttl` is fetched again after the response has been sent.
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

    $this->authorize = 'https://id.test/oauth/authorize';
    $this->claimedIssuer = 'https://id.test';
    $this->down = false;
    $this->hits = ['/.well-known/openid-configuration' => 0, '/.well-known/jwks.json' => 0];
    $authorize = &$this->authorize;
    $claimed = &$this->claimedIssuer;
    $down = &$this->down;
    $hits = &$this->hits;

    // Counted here, not with Http::recorded(): a request that fails to connect is not recorded.
    Http::fake([
        '*/.well-known/openid-configuration' => static function () use (&$authorize, &$claimed, &$down, &$hits) {
            $hits['/.well-known/openid-configuration']++;

            if ($down) {
                throw new ConnectionException('Connection refused');
            }

            return Http::response([
                'issuer' => $claimed,
                'authorization_endpoint' => $authorize,
                'jwks_uri' => 'https://id.test/.well-known/jwks.json',
            ]);
        },
        '*/.well-known/jwks.json' => static function () use (&$down, &$hits) {
            $hits['/.well-known/jwks.json']++;

            return $down ? throw new ConnectionException('Connection refused') : Http::response(rsaKeypair()['jwks']);
        },
    ]);
});

function discoveryRequests(string $suffix = '/.well-known/openid-configuration'): int
{
    return test()->hits[$suffix];
}

function keptDiscovery(int $ttl = 3600, ?int $keep = null): Discovery
{
    return new Discovery('https://id.test', $ttl, 10, $keep);
}

it('fetches the document once and then reads the kept copy', function (): void {
    keptDiscovery()->document();
    $this->travel(3599)->seconds();
    keptDiscovery()->document();
    defer()->invoke();

    expect(discoveryRequests())->toBe(1);
});

it('serves a stale document without a request and fetches it again after the response', function (): void {
    keptDiscovery()->document();
    $this->travel(2)->days();
    $this->authorize = 'https://id.test/oauth/authorize-v2';

    // The request: the kept copy, no call.
    expect(keptDiscovery()->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize')
        ->and(discoveryRequests())->toBe(1);

    // After the response.
    defer()->invoke();

    expect(discoveryRequests())->toBe(2)
        ->and(keptDiscovery()->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize-v2');
});

it('keeps the stale document when the refresh fails, and tries again a minute later', function (): void {
    Exceptions::fake();
    keptDiscovery()->document();
    $this->travel(2)->hours();
    $this->down = true;

    keptDiscovery()->document();
    defer()->invoke();
    keptDiscovery()->document();
    defer()->invoke();

    // One failed attempt, not one per read.
    expect(discoveryRequests())->toBe(2)
        ->and(keptDiscovery()->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize');

    $this->down = false;
    $this->travel(61)->seconds();
    keptDiscovery()->document();
    defer()->invoke();

    expect(discoveryRequests())->toBe(3);
});

it('fetches in the request again once the copy is older than cache_keep', function (): void {
    keptDiscovery(keep: 86400)->document();
    $this->travel(86401)->seconds();
    $this->authorize = 'https://id.test/oauth/authorize-v2';

    expect(keptDiscovery(keep: 86400)->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize-v2');
});

it('keeps for seven days by default, and never shorter than cache_ttl', function (): void {
    keptDiscovery()->document();
    $this->travel(6)->days();
    $this->down = true;

    expect(keptDiscovery()->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize');

    $this->travel(2)->days();

    expect(fn () => keptDiscovery()->document())->toThrow(ConnectionException::class);

    $this->down = false;
    keptDiscovery(ttl: 7200, keep: 60)->document();
    $this->travel(7000)->seconds();
    keptDiscovery(ttl: 7200, keep: 60)->document();

    expect(discoveryRequests())->toBe(3);
});

it('keeps the JWKS the same way', function (): void {
    keptDiscovery()->jwks();
    $this->travel(2)->days();

    expect(keptDiscovery()->jwks())->toBe(rsaKeypair()['jwks'])
        ->and(discoveryRequests('/.well-known/jwks.json'))->toBe(1);

    defer()->invoke();

    expect(discoveryRequests('/.well-known/jwks.json'))->toBe(2);
});

it('still refetches the JWKS at once on a kid miss', function (): void {
    keptDiscovery()->jwks();

    expect(keptDiscovery()->refreshJwks())->toBe(rsaKeypair()['jwks'])
        ->and(discoveryRequests('/.well-known/jwks.json'))->toBe(2)
        ->and(keptDiscovery()->refreshJwks())->toBeNull();
});

it('still refuses a refreshed document for another issuer and keeps the good one', function (): void {
    Exceptions::fake();
    keptDiscovery()->document();
    $this->travel(2)->hours();
    $this->claimedIssuer = 'https://evil.test';
    $this->authorize = 'https://evil.test/oauth/authorize';

    keptDiscovery()->document();
    defer()->invoke();

    expect(keptDiscovery()->endpoint('authorization_endpoint'))->toBe('https://id.test/oauth/authorize');
    Exceptions::assertReported(ClientConfigurationException::class);
});

it('does not cache at all with a cache_ttl of zero', function (): void {
    keptDiscovery(ttl: 0)->document();
    keptDiscovery(ttl: 0)->document();

    expect(discoveryRequests())->toBe(2);
});

it('refreshes stale copies on demand and leaves fresh ones alone', function (): void {
    $discovery = keptDiscovery();
    $discovery->document();
    $discovery->jwks();

    expect($discovery->refreshStale())->toBe(0);

    $this->travel(3601)->seconds();

    expect($discovery->refreshStale())->toBe(2)
        ->and(discoveryRequests())->toBe(2)
        ->and(discoveryRequests('/.well-known/jwks.json'))->toBe(2);
});

it('redirects to sign in without a remote call once discovery is kept', function (): void {
    app(IdentityClient::class)->redirect();
    $this->travel(3)->days();
    $before = Http::recorded()->count();

    $url = app(IdentityClient::class)->redirect()->getTargetUrl();

    expect($url)->toStartWith('https://id.test/oauth/authorize?')
        ->and(Http::recorded()->count())->toBe($before);
});
