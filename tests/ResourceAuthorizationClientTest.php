<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\PersonalAppCatalog;
use Cbox\Id\Client\ResourceAuthorizationClient;
use Cbox\Id\Client\Support\Pkce;
use Cbox\Id\Client\Testing\FakeDiscovery;
use Cbox\Id\Client\Testing\TestKeys;
use Cbox\Id\Client\Testing\TokenFactory;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->client = new ResourceAuthorizationClient(['issuer' => 'https://id.example', 'client_id' => 'sites', 'client_secret' => 'secret'], new FakeDiscovery('https://id.example', TestKeys::pair()['jwks']));
    $this->token = (new TokenFactory('https://id.example', 'https://domains.example', 'sites'))->for('user-a')->organization('org-a')->scopes(['dns:write']);
});

function resourceStart($test, array $context = []): array
{
    parse_str(parse_url($test->client->redirect('https://domains.example', 'org-a', 'user-a', 'https://sites.example/callback', ['dns:write'], $context)->getTargetUrl(), PHP_URL_QUERY), $query);

    return $query;
}

it('exchanges PKCE with signed exact bindings and preserves independent tab state and private context', function (): void {
    $first = resourceStart($this, ['binding' => 'one']);
    $second = resourceStart($this, ['binding' => 'two']);
    session()->put('cbox-resource', json_decode(json_encode(session()->get('cbox-resource'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    $bearer = (string) $this->token;
    Http::fake(['https://id.example/oauth/token' => Http::response(['access_token' => $bearer, 'token_type' => 'Bearer'])]);
    expect($first['state'])->not->toBe($second['state'])->and($first)->not->toHaveKey('context');
    $result = $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code-b', 'state' => $second['state']]));
    expect($result->context)->toBe(['binding' => 'two'])->and($result->subject)->toBe('user-a')->and($result->accessToken())->toBe($bearer)
        ->and(json_encode($result))->not->toContain($result->accessToken());
    $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code-a', 'state' => $first['state']]));
    Http::assertSent(fn ($request) => $request['code'] === 'code-a' && Pkce::challenge($request['code_verifier']) === $first['code_challenge'] && $request['resource'] === 'https://domains.example');
    Http::assertSentCount(2);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['code' => 'again', 'state' => $first['state']])))->toThrow(InvalidState::class);
});

it('rejects signed tokens for another subject organization audience client or insufficient scope', function (string $mutation): void {
    $start = resourceStart($this);
    $token = match ($mutation) {
        'subject' => $this->token->for('user-b'), 'organization' => $this->token->organization('org-b'),
        'audience' => $this->token->audience('https://other.example'), 'client' => $this->token->client('other'),
        'scope' => $this->token->scopes([]), 'expired' => $this->token->expired(), 'actor' => $this->token->actor('staff'),
        'signature' => (string) $this->token.'corrupt',
    };
    Http::fake(['*' => Http::response(['access_token' => (string) $token, 'token_type' => 'Bearer'])]);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code', 'state' => $start['state']])))->toThrow(AuthenticationFailed::class);
})->with(['subject', 'organization', 'audience', 'client', 'scope', 'expired', 'actor', 'signature']);

it('refuses stale forged and error callbacks without exchanging credentials', function (): void {
    $start = resourceStart($this);
    $pending = session()->get('cbox-resource.'.$start['state']);
    $pending['expires'] = time() - 1;
    session()->put('cbox-resource.'.$start['state'], $pending);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code', 'state' => $start['state']])))->toThrow(InvalidState::class);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code', 'state' => str_repeat('a', 64)])))->toThrow(InvalidState::class);
    $start = resourceStart($this);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['error' => 'access_denied', 'state' => $start['state']])))->toThrow(AuthenticationFailed::class);
    Http::assertNothingSent();
});

it('rejects cookie sessions and bounds outstanding starts while pruning expired state', function (): void {
    config(['session.driver' => 'cookie']);
    expect(fn () => resourceStart($this))->toThrow(AuthenticationFailed::class);
    config(['session.driver' => 'array']);
    for ($i = 0; $i < 10; $i++) {
        resourceStart($this);
    }
    expect(fn () => resourceStart($this))->toThrow(AuthenticationFailed::class);
    foreach (session()->get('cbox-resource') as $key => $pending) {
        $pending['expires'] = time() - 1;
        session()->put('cbox-resource.'.$key, $pending);
    }
    expect(resourceStart($this))->toHaveKey('state');
    expect(session()->get('cbox-resource'))->toHaveCount(1);
    Http::assertNothingSent();
});

it('refuses a cookie-session callback without consuming a server transaction', function (): void {
    $driver = config('session.driver');
    $start = resourceStart($this);
    config(['session.driver' => 'cookie']);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['code' => 'code', 'state' => $start['state']])))->toThrow(AuthenticationFailed::class);
    config(['session.driver' => $driver]);
    expect(session()->has('cbox-resource.'.$start['state']))->toBeTrue();
    Http::assertNothingSent();
});

it('refuses URL credentials and an issuer-equivalent resource', function (string $resource, string $callback): void {
    expect(fn () => $this->client->redirect($resource, 'org-a', 'user-a', $callback, ['dns:write']))->toThrow(AuthenticationFailed::class);
    Http::assertNothingSent();
})->with([
    ['https://user:secret@domains.example', 'https://sites.example/callback'],
    ['https://domains.example', 'https://user:secret@sites.example/callback'],
    ['https://id.example/', 'https://sites.example/callback'],
]);

it('preserves existing authorization endpoint query parameters', function (): void {
    $discovery = new class('https://id.example', TestKeys::pair()['jwks']) extends FakeDiscovery
    {
        public function document(): array
        {
            return array_replace(parent::document(), ['authorization_endpoint' => 'https://id.example/oauth/authorize?tenant=one']);
        }
    };
    $this->client = new ResourceAuthorizationClient(['issuer' => 'https://id.example', 'client_id' => 'sites', 'client_secret' => 'secret'], $discovery);
    $query = resourceStart($this);
    expect($query['tenant'])->toBe('one')->and($query['response_type'])->toBe('code')->and($query)->toHaveKey('state');
});

it('exchanges and presents a sender-constrained grant with distinct signed proofs', function (): void {
    $url = $this->client->redirect('https://domains.example', 'org-a', 'user-a', 'https://sites.example/callback', ['dns:write'], dpop: true)->getTargetUrl();
    parse_str(parse_url($url, PHP_URL_QUERY), $start);
    $bound = (string) $this->token->with(['cnf' => ['jkt' => $start['dpop_jkt']]]);
    Http::fake(['*' => Http::response(['access_token' => $bound, 'token_type' => 'DPoP'])]);
    $grant = $this->client->authenticate(Request::create('/', 'GET', ['state' => $start['state'], 'code' => 'code']));
    $tokenProof = Http::recorded()[0][0]->header('DPoP')[0];
    $url = 'https://domains.example/api/v1/me/apps?organization_id=org-a';
    $headers = $grant->requestHeaders('GET', $url);
    expect($headers['Authorization'])->toBe('DPoP '.$bound);
    $decode = function (string $proof): object {
        $header = json_decode(base64_decode(strtr(explode('.', $proof)[0], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        expect($header['typ'])->toBe('dpop+jwt')->and($header['jwk'])->not->toHaveKey('d');

        return JWT::decode($proof, JWK::parseKey($header['jwk'], 'ES256'));
    };
    $tokenClaims = $decode($tokenProof);
    $apiClaims = $decode($headers['DPoP']);
    expect($tokenClaims->htm)->toBe('POST')->and(property_exists($tokenClaims, 'ath'))->toBeFalse()
        ->and($apiClaims->htm)->toBe('GET')->and($apiClaims->htu)->toBe('https://domains.example/api/v1/me/apps')
        ->and($apiClaims->ath)->toBe(rtrim(strtr(base64_encode(hash('sha256', $bound, true)), '+/', '-_'), '='))
        ->and($apiClaims->jti)->not->toBe($tokenClaims->jti)
        ->and($grant->requestHeaders('GET', $url)['DPoP'])->not->toBe($headers['DPoP']);
    expect(fn () => $grant->requestHeaders('GET', 'https://foreign.example/apps'))->toThrow(InvalidArgumentException::class);
});

it('refuses DPoP downgrade and a token bound to another holder', function (string $type, array $binding): void {
    $url = $this->client->redirect('https://domains.example', 'org-a', 'user-a', 'https://sites.example/callback', ['dns:write'], dpop: true)->getTargetUrl();
    parse_str(parse_url($url, PHP_URL_QUERY), $start);
    Http::fake(['*' => Http::response(['access_token' => (string) $this->token->with($binding), 'token_type' => $type])]);
    expect(fn () => $this->client->authenticate(Request::create('/', 'GET', ['state' => $start['state'], 'code' => 'code'])))->toThrow(AuthenticationFailed::class);
})->with([['Bearer', []], ['DPoP', []], ['DPoP', ['cnf' => ['jkt' => 'wrong']]]]);

it('reads only the personal catalog for the authorized subject and organization', function (): void {
    $grant = new ResourceAuthorizationResult('token', 'user-a', 'org-a', 'https://id.example/mcp', ['account:apps:read'], time() + 600, []);
    $catalog = new PersonalAppCatalog(app(Factory::class), 'https://id.example');
    Http::fake(['id.example/*' => Http::response(['data' => [['key' => 'signals', 'name' => 'Signals', 'description' => null, 'launch_url' => 'https://signals.example/', 'icon_url' => null]], 'meta' => ['revision' => str_repeat('a', 64), 'refresh_after' => 180]])]);
    expect(fn () => $catalog->read($grant, 'user-b', 'org-a'))->toThrow(AuthenticationFailed::class);
    expect(fn () => $catalog->read($grant, 'user-a', 'org-b'))->toThrow(AuthenticationFailed::class);
    Http::assertNothingSent();
    $page = $catalog->read($grant, 'user-a', 'org-a');
    expect($page['refresh_after'])->toBe(60)->and($page['apps'][0]['key'])->toBe('signals');
    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer token') && $req->url() === 'https://id.example/api/v1/me/apps?organization_id=org-a');
});
