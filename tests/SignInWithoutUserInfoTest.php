<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * A sign-in callback is one remote call, the code exchange, when the verified id_token
 * already answers what the requested scopes promise. UserInfo is the fallback for an
 * id_token that does not.
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

/** @param array<string, mixed> $claims */
function signedIdToken(array $claims = []): string
{
    return idToken($claims + [
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
    ]);
}

/** @param list<string>|null $scopes */
function callbackFor(?array $scopes): Request
{
    $request = loginCallback();

    if ($scopes !== null) {
        session(['cbox-id-client.scopes' => $scopes]);
    }

    return $request;
}

function userinfoCalls(): int
{
    return Http::recorded(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/userinfo'))->count();
}

it('does not call UserInfo when the id_token carries what the scopes promise', function (): void {
    fakeCbox(signedIdToken([
        'email' => 'grace@id.test', 'email_verified' => true, 'name' => 'Grace',
        'org' => 'org_9', 'org_name' => 'Nine', 'org_role' => 'admin',
        'roles' => ['editor'], 'permissions' => ['posts:write'],
    ]));

    $user = app(IdentityClient::class)->authenticate(callbackFor(['openid', 'profile', 'email']));

    expect(userinfoCalls())->toBe(0)
        ->and($user->id)->toBe('user-1')
        ->and($user->email)->toBe('grace@id.test')
        ->and($user->name)->toBe('Grace')
        ->and($user->organizationId)->toBe('org_9')
        ->and($user->permissions())->toBe(['posts:write'])
        ->and(app(SessionIdentityStore::class)->identity()?->roles())->toBe(['editor']);
});

it('calls UserInfo when the id_token lacks a claim a requested scope promises', function (array $scopes, array $claims): void {
    fakeCbox(signedIdToken($claims));

    $user = app(IdentityClient::class)->authenticate(callbackFor($scopes));

    // Asked, and what it answered fills what the id_token left out.
    expect(userinfoCalls())->toBe(1)
        ->and($user->name)->toBe($claims['name'] ?? 'Ada');
})->with([
    'a minimal id_token' => [['openid', 'profile', 'email'], []],
    'no email' => [['openid', 'profile', 'email'], ['name' => 'Grace']],
    'no name' => [['openid', 'profile', 'email'], ['email' => 'grace@id.test']],
    'no organizations' => [['openid', 'email', 'organizations'], ['email' => 'grace@id.test']],
    'openid alone, nothing UserInfo-like' => [['openid'], ['org' => 'org_9']],
    'a claim of the wrong type' => [['openid', 'email'], ['email' => ['grace@id.test']]],
]);

it('takes an empty organizations list in the id_token as an answer', function (): void {
    fakeCbox(signedIdToken(['email' => 'grace@id.test', 'organizations' => []]));

    app(IdentityClient::class)->authenticate(callbackFor(['openid', 'email', 'organizations']));

    expect(userinfoCalls())->toBe(0);
});

it('skips UserInfo for openid alone once the id_token carries roles', function (): void {
    fakeCbox(signedIdToken(['roles' => ['editor'], 'permissions' => []]));

    app(IdentityClient::class)->authenticate(callbackFor(['openid']));

    expect(userinfoCalls())->toBe(0);
});

it('always calls UserInfo when configured to', function (): void {
    config(['cbox-id-client.userinfo' => 'always']);
    fakeCbox(signedIdToken(['email' => 'grace@id.test', 'name' => 'Grace']));

    $user = app(IdentityClient::class)->authenticate(callbackFor(['openid', 'profile', 'email']));

    // Enriches, never replaces: the signed claims still win.
    expect(userinfoCalls())->toBe(1)
        ->and($user->email)->toBe('grace@id.test');
});

it('judges a session that predates the scopes key by the configured scopes', function (): void {
    fakeCbox(signedIdToken(['email' => 'grace@id.test', 'name' => 'Grace']));

    app(IdentityClient::class)->authenticate(callbackFor(null));

    expect(userinfoCalls())->toBe(0);
});

it('still refuses an openid sign-in that returns no id_token', function (): void {
    fakeCbox(null);

    expect(fn () => app(IdentityClient::class)->authenticate(callbackFor(['openid', 'email'])))
        ->toThrow(AuthenticationFailed::class, 'no id_token');

    expect(userinfoCalls())->toBe(0);
});

it('still refuses a UserInfo answer for another subject when it has to ask', function (): void {
    fakeCbox(signedIdToken(['sub' => 'user-2']));

    expect(fn () => app(IdentityClient::class)->authenticate(callbackFor(['openid', 'email'])))
        ->toThrow(AuthenticationFailed::class, 'does not match');
});

it('still binds the sign-in to the requested organization without UserInfo', function (): void {
    fakeCbox(signedIdToken(['email' => 'grace@id.test', 'org' => 'org_other']));
    $request = callbackFor(['openid', 'email']);
    session(['cbox-id-client.organization' => 'org_9']);

    expect(fn () => app(IdentityClient::class)->authenticate($request))
        ->toThrow(AuthenticationFailed::class, 'different organization');
});

it('remembers a refresh from the refreshed id_token without UserInfo', function (): void {
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'token_endpoint' => 'https://id.test/oauth/token',
            'userinfo_endpoint' => 'https://id.test/oauth/userinfo',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/.well-known/jwks.json' => Http::response(rsaKeypair()['jwks']),
        '*/oauth/token' => Http::response([
            'access_token' => 'at_2', 'refresh_token' => 'rt_2', 'scope' => 'openid email',
            'id_token' => idToken([
                'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'iat' => time(), 'exp' => time() + 900,
                'email' => 'grace@id.test', 'permissions' => ['a:read'],
            ]),
        ]),
        '*/oauth/userinfo' => Http::response(['sub' => 'user-1', 'permissions' => ['from:userinfo']]),
    ]);

    app(IdentityClient::class)->refresh('rt_1', remember: true);

    expect(userinfoCalls())->toBe(0)
        ->and(app(SessionIdentityStore::class)->identity()?->permissions())->toBe(['a:read']);
});

it('makes one remote call, the code exchange, for a returning person days later', function (): void {
    config(['cbox-id-client.app_catalog.enabled' => true]);
    Queue::fake();
    $claims = ['email' => 'grace@id.test', 'name' => 'Grace', 'org' => 'org_9', 'roles' => ['editor']];
    fakeCbox(static fn (): string => signedIdToken($claims));

    // The first sign-in this app ever sees: discovery, JWKS and the exchange.
    app(IdentityClient::class)->authenticate(callbackFor(['openid', 'profile', 'email']));
    expect(Http::recorded()->count())->toBe(3);

    $this->travel(3)->days();
    $before = Http::recorded()->count();

    app(IdentityClient::class)->redirect();
    app(IdentityClient::class)->authenticate(callbackFor(['openid', 'profile', 'email']));

    $urls = Http::recorded()->slice($before)->map(static fn (array $pair): string => $pair[0]->url())->values()->all();

    expect($urls)->toBe(['https://id.test/oauth/token']);
});
