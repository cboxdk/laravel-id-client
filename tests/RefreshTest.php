<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Cbox\Id\Client\ValueObjects\Identity;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The refresh-token grant, with the same behaviour as the JavaScript SDK: rotation
 * honoured, a returned id_token verified, and `invalid_grant` told apart from an outage.
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

/** @param array<string, mixed> $tokenResponse */
function fakeRefresh(array $tokenResponse, int $status = 200, array $userinfo = ['sub' => 'user-1']): void
{
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'token_endpoint' => 'https://id.test/oauth/token',
            'userinfo_endpoint' => 'https://id.test/oauth/userinfo',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/.well-known/jwks.json' => Http::response(rsaKeypair()['jwks']),
        '*/oauth/token' => Http::response($tokenResponse, $status),
        '*/oauth/userinfo' => Http::response($userinfo),
    ]);
}

it('sends a refresh_token grant and hands back the rotated token', function (): void {
    fakeRefresh(['access_token' => 'at_2', 'refresh_token' => 'rt_2', 'expires_in' => 900, 'scope' => 'openid offline_access']);

    $tokens = app(IdentityClient::class)->refresh('rt_1');

    expect($tokens->accessToken)->toBe('at_2')
        ->and($tokens->refreshToken)->toBe('rt_2')
        ->and($tokens->expiresIn)->toBe(900)
        ->and($tokens->scope)->toBe('openid offline_access');

    Http::assertSent(fn (HttpRequest $r): bool => str_ends_with($r->url(), '/oauth/token')
        && $r['grant_type'] === 'refresh_token'
        && $r['refresh_token'] === 'rt_1'
        && $r['client_id'] === 'client_1'
        && $r['client_secret'] === 'secret_1');
});

it('keeps the presented refresh token when the server does not rotate', function (): void {
    fakeRefresh(['access_token' => 'at_2']);

    expect(app(IdentityClient::class)->refresh('rt_1')->refreshToken)->toBe('rt_1');
});

it('refreshes a public client without a secret', function (): void {
    config(['cbox-id-client.client_secret' => null]);
    fakeRefresh(['access_token' => 'at_2']);

    app(IdentityClient::class)->refresh('rt_1');

    Http::assertSent(fn (HttpRequest $r): bool => str_ends_with($r->url(), '/oauth/token') && ! isset($r['client_secret']));
});

it('verifies a returned id_token and hands back its claims', function (): void {
    fakeRefresh(['access_token' => 'at_2', 'id_token' => idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'org' => 'org_1', 'iat' => time(), 'exp' => time() + 900,
    ])]);

    expect(app(IdentityClient::class)->refresh('rt_1')->claims)->toMatchArray(['sub' => 'user-1', 'org' => 'org_1']);
});

it('refuses a returned id_token for another audience', function (): void {
    fakeRefresh(['access_token' => 'at_2', 'id_token' => idToken([
        'iss' => 'https://id.test', 'aud' => 'someone_else', 'sub' => 'user-1', 'iat' => time(), 'exp' => time() + 900,
    ])]);

    expect(fn () => app(IdentityClient::class)->refresh('rt_1'))
        ->toThrow(AuthenticationFailed::class, 'The id_token audience did not match.');
});

it('tells a spent refresh token apart from an outage', function (): void {
    fakeRefresh(['error' => 'invalid_grant', 'error_description' => 'Refresh token reuse detected.'], 400);

    try {
        app(IdentityClient::class)->refresh('rt_1');
        $this->fail('Expected a refusal.');
    } catch (AuthenticationFailed $e) {
        expect($e->isInvalidGrant())->toBeTrue()
            ->and($e->errorDescription)->toBe('Refresh token reuse detected.');
    }
});

it('does not call an outage a spent refresh token', function (): void {
    fakeRefresh(['error' => 'temporarily_unavailable'], 503);

    try {
        app(IdentityClient::class)->refresh('rt_1');
        $this->fail('Expected a refusal.');
    } catch (AuthenticationFailed $e) {
        expect($e->isInvalidGrant())->toBeFalse()->and($e->status)->toBe(503);
    }
});

it('updates what the session remembers when asked to', function (): void {
    app(SessionIdentityStore::class)->remember(new Identity('user-1', ['org' => 'org_1', 'permissions' => ['a:read']]));
    fakeRefresh(['access_token' => 'at_2'], 200, ['sub' => 'user-1', 'org' => 'org_1', 'permissions' => ['a:read', 'a:write']]);

    app(IdentityClient::class)->refresh('rt_1', remember: true);

    expect(app(SessionIdentityStore::class)->identity()?->permissions())->toBe(['a:read', 'a:write']);
});

it('will not let a refresh swap the remembered person for somebody else', function (): void {
    app(SessionIdentityStore::class)->remember(new Identity('user-1', ['permissions' => ['a:read']]));
    fakeRefresh(['access_token' => 'at_2'], 200, ['sub' => 'user-2', 'permissions' => ['admin:everything']]);

    expect(fn () => app(IdentityClient::class)->refresh('rt_1', remember: true))
        ->toThrow(AuthenticationFailed::class, 'different subject than this session');

    expect(app(SessionIdentityStore::class)->identity()?->subject)->toBe('user-1');
});
