<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\BackchannelAuthentication;
use Cbox\Id\Client\Contracts\DeviceAuthorization;
use Cbox\Id\Client\Enums\ClientAuthMethod;
use Cbox\Id\Client\Exceptions\AuthorizationDenied;
use Cbox\Id\Client\Exceptions\AuthorizationExpired;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\GrantFailed;
use Cbox\Id\Client\Exceptions\GrantRateLimited;
use Cbox\Id\Client\Exceptions\GrantTimedOut;
use Cbox\Id\Client\Exceptions\InvalidBindingMessage;
use Cbox\Id\Client\Exceptions\InvalidClient;
use Cbox\Id\Client\Exceptions\InvalidGrant;
use Cbox\Id\Client\Exceptions\InvalidTarget;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\UnknownUser;
use Cbox\Id\Client\Grants\ClientAuthentication;
use Cbox\Id\Client\Grants\Timer;
use Cbox\Id\Client\Testing\FakeTimer;
use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;
use Cbox\Id\Client\ValueObjects\PendingDeviceAuthorization;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const GRANT_ISSUER = 'https://id.test';

beforeEach(function (): void {
    config()->set('cbox-id-client.issuer', GRANT_ISSUER);
    config()->set('cbox-id-client.client_id', 'cid_agent');
    config()->set('cbox-id-client.client_secret', 'csec_agent');

    $this->timer = new FakeTimer;
    $this->app->instance(Timer::class, $this->timer);
});

/** @return array<string, mixed> */
function grantMetadata(array $overrides = []): array
{
    return array_merge([
        'issuer' => GRANT_ISSUER,
        'token_endpoint' => GRANT_ISSUER.'/oauth/token',
        'backchannel_authentication_endpoint' => GRANT_ISSUER.'/oauth/backchannel_authentication',
        'device_authorization_endpoint' => GRANT_ISSUER.'/oauth/device_authorization',
    ], $overrides);
}

function tokenBody(array $overrides = []): array
{
    return array_merge([
        'access_token' => 'at_123',
        'token_type' => 'Bearer',
        'expires_in' => 900,
        'scope' => 'mcp:read mcp:write',
        'refresh_token' => 'rt_123',
        'id_token' => 'eyJ.id.token',
    ], $overrides);
}

function cibaStarted(int $interval = 5, int $expiresIn = 120): array
{
    return ['auth_req_id' => 'areq_1', 'expires_in' => $expiresIn, 'interval' => $interval];
}

function cibaGrant(): BackchannelAuthentication
{
    return app(BackchannelAuthentication::class);
}

function grantPending(int $interval = 5, int $expiresAt = 1_700_000_120, ?string $resource = 'https://app.cboxbrain.com/mcp'): PendingBackchannelAuthentication
{
    return new PendingBackchannelAuthentication('areq_1', 120, $interval, $expiresAt, $resource);
}

/** @return array<string, string> */
function grantForm(Request $request): array
{
    parse_str($request->body(), $data);

    return array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $data);
}

it('starts a backchannel request at the discovered endpoint with Basic client auth', function (): void {
    Http::fake([
        'id.test/.well-known/oauth-authorization-server' => Http::response(grantMetadata([
            'backchannel_authentication_endpoint' => GRANT_ISSUER.'/custom/bc',
        ])),
        'id.test/custom/bc' => Http::response(cibaStarted(interval: 3)),
    ]);

    $pending = cibaGrant()->start('usr_01HZY', 'mcp:read mcp:write', 'Let the agent save notes to Cbox Brain', 'https://app.cboxbrain.com/mcp', 300, 'n-1');

    expect($pending->authReqId)->toBe('areq_1')
        ->and($pending->interval)->toBe(3)
        ->and($pending->expiresIn)->toBe(120)
        ->and($pending->expiresAt)->toBe(1_700_000_120)
        ->and($pending->resource)->toBe('https://app.cboxbrain.com/mcp');

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== GRANT_ISSUER.'/custom/bc') {
            return false;
        }

        expect(grantForm($request))->toBe([
            'login_hint' => 'usr_01HZY',
            'scope' => 'mcp:read mcp:write',
            'binding_message' => 'Let the agent save notes to Cbox Brain',
            'resource' => 'https://app.cboxbrain.com/mcp',
            'requested_expiry' => '300',
            'nonce' => 'n-1',
        ])->and($request->header('Authorization')[0])->toBe('Basic '.base64_encode('cid_agent:csec_agent'));

        return true;
    });
});

it('sends a client_notification_token for a ping-mode client, and none otherwise', function (): void {
    Http::fake([
        'id.test/.well-known/oauth-authorization-server' => Http::response(grantMetadata()),
        'id.test/oauth/backchannel_authentication' => Http::response(cibaStarted()),
    ]);

    cibaGrant()->start('usr_1', 'openid', 'plan 3f9a2c41d0b7', clientNotificationToken: 'ping-bearer');
    cibaGrant()->start('usr_1', 'openid', 'plan 3f9a2c41d0b7');

    $sent = Http::recorded()
        ->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/oauth/backchannel_authentication'))
        ->map(fn (array $pair): array => grantForm($pair[0]))
        ->values();

    expect($sent[0]['client_notification_token'] ?? null)->toBe('ping-bearer')
        ->and($sent[1])->not->toHaveKey('client_notification_token');
});

it('refuses a client_notification_token over 1024 characters before calling Cbox ID', function (): void {
    Http::fake();

    expect(fn () => cibaGrant()->start('usr_1', 'openid', 'x', clientNotificationToken: str_repeat('a', 1025)))
        ->toThrow(GrantFailed::class);

    Http::assertNothingSent();
});

it('falls back to the default paths when the issuer publishes no metadata', function (): void {
    Http::fake([
        'id.test/.well-known/oauth-authorization-server' => Http::response('', 404),
        'id.test/oauth/backchannel_authentication' => Http::response(cibaStarted()),
    ]);

    expect(cibaGrant()->start('ada@example.com', 'openid', 'Sign in to the CLI')->authReqId)->toBe('areq_1');
});

it('refuses a metadata document for another issuer', function (): void {
    Http::fake(['id.test/.well-known/oauth-authorization-server' => Http::response(grantMetadata(['issuer' => 'https://evil.test']))]);

    cibaGrant()->start('usr_1', 'openid', 'x');
})->throws(ClientConfigurationException::class, 'different issuer');

it('refuses a plain-http issuer and a missing one', function (): void {
    config()->set('cbox-id-client.issuer', 'http://id.test');
    expect(fn () => cibaGrant()->start('usr_1', 'openid', 'x'))->toThrow(ClientConfigurationException::class, 'https');

    config()->set('cbox-id-client.issuer', '');
    expect(fn () => cibaGrant()->start('usr_1', 'openid', 'x'))->toThrow(NotConfigured::class);
});

it('refuses a binding message Cbox ID cannot show before sending anything', function (): void {
    Http::fake();

    expect(fn () => cibaGrant()->start('usr_1', 'openid', str_repeat('x', 256)))->toThrow(InvalidBindingMessage::class);
    Http::assertNothingSent();
});

it('polls pending, slows down by five seconds, then returns the tokens', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::sequence()
            ->push(['error' => 'authorization_pending'], 400)
            ->push(['error' => 'slow_down'], 400)
            ->push(tokenBody()),
    ]);

    $first = cibaGrant()->poll(grantPending());
    $second = cibaGrant()->poll(grantPending(), $first->interval);
    $third = cibaGrant()->poll(grantPending(), $second->interval);

    expect($first->granted())->toBeFalse()->and($first->interval)->toBe(5)->and($first->slowDown)->toBeFalse()
        ->and($second->granted())->toBeFalse()->and($second->interval)->toBe(10)->and($second->slowDown)->toBeTrue()
        ->and($third->tokens?->accessToken)->toBe('at_123')
        ->and($third->tokens?->tokenType)->toBe('Bearer')
        ->and($third->tokens?->expiresIn)->toBe(900)
        ->and($third->tokens?->scopes())->toBe(['mcp:read', 'mcp:write'])
        ->and($third->tokens?->refreshToken)->toBe('rt_123')
        ->and($third->tokens?->idToken)->toBe('eyJ.id.token');

    Http::assertSent(fn (Request $request): bool => $request->url() === GRANT_ISSUER.'/oauth/token'
        && grantForm($request) === [
            'grant_type' => 'urn:openid:params:grant-type:ciba',
            'auth_req_id' => 'areq_1',
            'resource' => 'https://app.cboxbrain.com/mcp',
        ]);
});

it('waits on the interval, honours slow_down, and never polls early', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::sequence()
            ->push(['error' => 'authorization_pending'], 400)
            ->push(['error' => 'slow_down'], 400)
            ->push(['error' => 'authorization_pending'], 400)
            ->push(tokenBody()),
    ]);

    $tokens = cibaGrant()->waitForToken(grantPending());

    expect($tokens->accessToken)->toBe('at_123')
        ->and($this->timer->sleeps)->toBe([5, 5, 10, 10]);
});

it('takes a longer Retry-After on a 429 while polling', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::response(['error' => 'slow_down'], 429, ['Retry-After' => '30']),
    ]);

    $result = cibaGrant()->poll(grantPending());

    expect($result->slowDown)->toBeTrue()->and($result->interval)->toBe(30);
});

it('stops at the maximum wait without sleeping past it', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::response(['error' => 'authorization_pending'], 400),
    ]);

    expect(fn () => cibaGrant()->waitForToken(grantPending(), maxWait: 12))->toThrow(GrantTimedOut::class);
    expect($this->timer->sleeps)->toBe([5, 5]);
});

it('stops when the request expires locally', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::response(['error' => 'authorization_pending'], 400),
    ]);

    expect(fn () => cibaGrant()->waitForToken(grantPending(expiresAt: 1_700_000_012)))->toThrow(AuthorizationExpired::class);
    expect($this->timer->sleeps)->toBe([5, 5]);
});

it('raises a typed exception for each OAuth error', function (string $error, int $status, string $class): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::response(['error' => $error, 'error_description' => 'Because.'], $status),
    ]);

    try {
        cibaGrant()->poll(grantPending());
        $this->fail('Expected '.$class);
    } catch (GrantFailed $e) {
        expect($e)->toBeInstanceOf($class)
            ->and($e->error)->toBe($error)
            ->and($e->description)->toBe('Because.')
            ->and($e->status)->toBe($status);
    }
})->with([
    'access_denied' => ['access_denied', 400, AuthorizationDenied::class],
    'expired_token' => ['expired_token', 400, AuthorizationExpired::class],
    'invalid_grant' => ['invalid_grant', 400, InvalidGrant::class],
    'invalid_target' => ['invalid_target', 400, InvalidTarget::class],
    'invalid_client' => ['invalid_client', 401, InvalidClient::class],
    'unknown_user_id' => ['unknown_user_id', 400, UnknownUser::class],
    'invalid_binding_message' => ['invalid_binding_message', 400, InvalidBindingMessage::class],
    'invalid_scope' => ['invalid_scope', 400, GrantFailed::class],
]);

it('maps errors at the backchannel endpoint too', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/backchannel_authentication' => Http::response(['error' => 'unknown_user_id'], 400),
    ]);

    cibaGrant()->start('nobody@example.com', 'openid', 'x');
})->throws(UnknownUser::class);

it('raises a rate limit with Retry-After when starting is throttled', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/backchannel_authentication' => Http::response(['error' => 'slow_down', 'error_description' => 'Too many requests for this person.'], 429, ['Retry-After' => '42']),
    ]);

    try {
        cibaGrant()->start('usr_1', 'openid', 'x');
        $this->fail('Expected GrantRateLimited');
    } catch (GrantRateLimited $e) {
        expect($e->retryAfter)->toBe(42)->and($e->error)->toBe('slow_down')->and($e->status)->toBe(429);
    }
});

it('refuses malformed answers instead of guessing', function (array $body, string $endpoint): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/*' => Http::response($body),
    ]);

    $endpoint === 'start' ? cibaGrant()->start('usr_1', 'openid', 'x') : cibaGrant()->poll(grantPending());
})->with([
    'no auth_req_id' => [['expires_in' => 60], 'start'],
    'string expires_in' => [['auth_req_id' => 'a', 'expires_in' => '60'], 'start'],
    'no access token' => [['token_type' => 'Bearer'], 'poll'],
    'no token type' => [['access_token' => 'at'], 'poll'],
    'a non-JSON server error' => [[], 'poll'],
])->throws(GrantFailed::class);

it('signs a fresh RS256 client assertion for every request', function (): void {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $public = openssl_pkey_get_details($key)['key'];

    config()->set('cbox-id-client.client_secret', null);
    config()->set('cbox-id-client.client_authentication.private_key', $pem);
    config()->set('cbox-id-client.client_authentication.key_id', 'key-1');

    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/backchannel_authentication' => Http::response(cibaStarted()),
        'id.test/oauth/token' => Http::response(['error' => 'authorization_pending'], 400),
    ]);

    cibaGrant()->poll(cibaGrant()->start('usr_1', 'openid', 'x'));

    $jtis = [];

    Http::assertSent(function (Request $request) use ($public, &$jtis): bool {
        if (str_contains($request->url(), '.well-known')) {
            return true;
        }

        $form = grantForm($request);
        expect($request->hasHeader('Authorization'))->toBeFalse()
            ->and($form['client_id'])->toBe('cid_agent')
            ->and($form['client_assertion_type'])->toBe('urn:ietf:params:oauth:client-assertion-type:jwt-bearer')
            ->and($form)->not->toHaveKey('client_secret');

        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $form['client_assertion'])[0]), true);
        $claims = JWT::decode($form['client_assertion'], new Key($public, 'RS256'));

        expect($header['kid'])->toBe('key-1')
            ->and($claims->iss)->toBe('cid_agent')
            ->and($claims->sub)->toBe('cid_agent')
            ->and($claims->aud)->toBe(GRANT_ISSUER.'/oauth/token')
            ->and($claims->exp - $claims->iat)->toBeLessThanOrEqual(60);

        $jtis[] = $claims->jti;

        return true;
    });

    expect($jtis)->toHaveCount(2)->and(array_unique($jtis))->toHaveCount(2);
});

it('signs an ES256 client assertion with a P-256 key', function (): void {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    $public = openssl_pkey_get_details($key)['key'];

    $params = ClientAuthentication::privateKeyJwt('cid_agent', $pem, 'ec-1', 'ES256')->parameters('https://id.test/oauth/token');
    $claims = JWT::decode($params['client_assertion'], new Key($public, 'ES256'));

    expect($claims->aud)->toBe('https://id.test/oauth/token')->and($claims->iss)->toBe('cid_agent');
});

it('refuses a signing key that does not fit the algorithm', function (string $type, string $algorithm): void {
    $key = $type === 'ec'
        ? openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'])
        : openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);

    ClientAuthentication::privateKeyJwt('cid_agent', $pem, null, $algorithm);
})->with([
    'EC key for RS256' => ['ec', 'RS256'],
    'RSA key for ES256' => ['rsa', 'ES256'],
    'HS256' => ['rsa', 'HS256'],
])->throws(ClientConfigurationException::class);

it('sends client_secret_post and public-client credentials in the body', function (): void {
    expect(ClientAuthentication::clientSecretPost('cid', 'sec')->parameters('aud'))->toBe(['client_id' => 'cid', 'client_secret' => 'sec'])
        ->and(ClientAuthentication::none('cid')->parameters('aud'))->toBe(['client_id' => 'cid'])
        ->and(ClientAuthentication::clientSecretBasic('cid', 'sec')->parameters('aud'))->toBe([]);
});

it('picks the client authentication method from config', function (array $auth, ?string $secret, ClientAuthMethod $expected): void {
    $method = ClientAuthentication::fromConfig(['client_id' => 'cid', 'client_secret' => $secret, 'client_authentication' => $auth])->method;

    expect($method)->toBe($expected);
})->with([
    'secret → basic' => [[], 'sec', ClientAuthMethod::ClientSecretBasic],
    'nothing → public' => [[], null, ClientAuthMethod::None],
    'explicit post' => [['method' => 'client_secret_post'], 'sec', ClientAuthMethod::ClientSecretPost],
]);

it('keeps secrets out of var_dump output', function (): void {
    expect(print_r(ClientAuthentication::clientSecretPost('cid', 'super-secret'), true))->not->toContain('super-secret');
});

it('round-trips a pending request through a session-safe array', function (): void {
    $pending = grantPending();

    expect(PendingBackchannelAuthentication::fromArray($pending->toArray()))->toEqual($pending);
});

it('runs the device grant end to end', function (): void {
    config()->set('cbox-id-client.client_secret', null);

    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/device_authorization' => Http::response([
            'device_code' => 'dc_1',
            'user_code' => 'WDJB-MJHT',
            'verification_uri' => GRANT_ISSUER.'/device',
            'verification_uri_complete' => GRANT_ISSUER.'/device?user_code=WDJB-MJHT',
            'expires_in' => 600,
        ]),
        'id.test/oauth/token' => Http::sequence()
            ->push(['error' => 'authorization_pending'], 400)
            ->push(tokenBody(['refresh_token' => null, 'id_token' => null])),
    ]);

    $device = app(DeviceAuthorization::class);
    $pending = $device->start('mcp:read mcp:write', 'https://app.cboxbrain.com/mcp');

    expect($pending->userCode)->toBe('WDJB-MJHT')
        ->and($pending->verificationUriComplete)->toBe(GRANT_ISSUER.'/device?user_code=WDJB-MJHT')
        ->and($pending->interval)->toBe(5)
        ->and($pending->expiresAt)->toBe(1_700_000_600);

    $tokens = $device->waitForToken($pending);

    expect($tokens->accessToken)->toBe('at_123')->and($tokens->refreshToken)->toBeNull()
        ->and($this->timer->sleeps)->toBe([5, 5]);

    Http::assertSent(fn (Request $request): bool => $request->url() === GRANT_ISSUER.'/oauth/device_authorization'
        && grantForm($request) === ['scope' => 'mcp:read mcp:write', 'resource' => 'https://app.cboxbrain.com/mcp', 'client_id' => 'cid_agent']);
    Http::assertSent(fn (Request $request): bool => $request->url() === GRANT_ISSUER.'/oauth/token'
        && grantForm($request) === [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'device_code' => 'dc_1',
            'resource' => 'https://app.cboxbrain.com/mcp',
            'client_id' => 'cid_agent',
        ]);
});

it('raises a typed exception when the person denies the device', function (): void {
    Http::fake([
        'id.test/.well-known/*' => Http::response(grantMetadata()),
        'id.test/oauth/token' => Http::response(['error' => 'access_denied'], 400),
    ]);

    app(DeviceAuthorization::class)->poll(new PendingDeviceAuthorization('dc_1', 'X', 'https://id.test/device', null, 600, 5, 1_700_000_600));
})->throws(AuthorizationDenied::class);
