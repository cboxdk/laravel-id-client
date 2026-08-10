<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A stable RSA keypair per `kid`, with its JWKS view. `$declareAlg` false serves a
 * key WITHOUT the optional `alg` member (RFC 7517 §4.4), so verification must supply
 * its own default.
 *
 * @return array{private: string, jwks: array<string, mixed>}
 */
function rsaKeypair(string $kid = 'test-1', bool $declareAlg = true): array
{
    static $keys = [];

    if (! isset($keys[$kid])) {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $priv);
        $details = openssl_pkey_get_details($res);

        $b64 = static fn (string $b): string => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
        $keys[$kid] = ['private' => $priv, 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])];
    }

    $jwk = ['kty' => 'RSA', 'use' => 'sig', 'kid' => $kid, 'n' => $keys[$kid]['n'], 'e' => $keys[$kid]['e']];

    if ($declareAlg) {
        $jwk['alg'] = 'RS256';
    }

    return ['private' => $keys[$kid]['private'], 'jwks' => ['keys' => [$jwk]]];
}

/**
 * @param  array<string, mixed>  $claims
 */
function idToken(array $claims, string $kid = 'test-1'): string
{
    return JWT::encode($claims, rsaKeypair($kid)['private'], 'RS256', $kid);
}

/**
 * Stub a Cbox ID instance. `$idToken` and `$jwks` accept a Closure so a single fake
 * can serve changing values across calls — `Http::fake()` resets recorded requests,
 * so a test that counts them must set the stubs up exactly once.
 *
 * @param  string|Closure|null  $idToken  the id_token the token endpoint returns
 * @param  array<string, mixed>|Closure|null  $jwks  overrides what the JWKS URI serves
 */
function fakeCbox(string|Closure|null $idToken = null, array|Closure|null $jwks = null): void
{
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'authorization_endpoint' => 'https://id.test/oauth/authorize',
            'token_endpoint' => 'https://id.test/oauth/token',
            'userinfo_endpoint' => 'https://id.test/oauth/userinfo',
            'introspection_endpoint' => 'https://id.test/oauth/introspect',
            'revocation_endpoint' => 'https://id.test/oauth/revoke',
            'end_session_endpoint' => 'https://id.test/oauth/logout',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/.well-known/jwks.json' => match (true) {
            $jwks instanceof Closure => $jwks,
            is_array($jwks) => Http::response($jwks),
            default => Http::response(rsaKeypair()['jwks']),
        },
        '*/oauth/token' => static fn () => Http::response([
            'access_token' => 'at_1',
            'id_token' => $idToken instanceof Closure ? $idToken() : $idToken,
            'refresh_token' => 'rt_1',
            'expires_in' => 900,
        ]),
        '*/oauth/userinfo' => Http::response(['sub' => 'user-1', 'email' => 'ada@id.test', 'name' => 'Ada', 'org' => 'org_1']),
        '*/oauth/introspect' => Http::response(['active' => true, 'sub' => 'user-1']),
        // RFC 7009: a successful revocation carries an empty 200 body.
        '*/oauth/revoke' => Http::response('', 200),
    ]);
}

/** Seed the session a callback needs and build the matching callback request. */
function loginCallback(): Request
{
    session([
        'cbox-id-client.state' => 'st_1',
        'cbox-id-client.verifier' => 'ver_1',
        'cbox-id-client.nonce' => 'nonce_1',
    ]);

    return Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']);
}

function jwksRequestCount(): int
{
    return Http::recorded(
        static fn ($request): bool => str_contains($request->url(), '/.well-known/jwks.json')
    )->count();
}

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    Cache::flush();
});

it('builds a PKCE authorize redirect and stashes state/verifier/nonce', function (): void {
    fakeCbox();

    $response = app(IdentityClient::class)->redirect();
    $url = $response->getTargetUrl();

    expect($url)->toStartWith('https://id.test/oauth/authorize?')
        ->and($url)->toContain('code_challenge_method=S256')
        ->and($url)->toContain('client_id=client_1')
        ->and(session('cbox-id-client.verifier'))->toBeString()
        ->and(session('cbox-id-client.state'))->toBeString();
});

it('completes login: verifies the id_token and returns the user', function (): void {
    session([
        'cbox-id-client.state' => 'st_1',
        'cbox-id-client.verifier' => 'ver_1',
        'cbox-id-client.nonce' => 'nonce_1',
    ]);
    fakeCbox(idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
    ]));

    $request = Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']);
    $user = app(IdentityClient::class)->authenticate($request);

    expect($user->id)->toBe('user-1')
        ->and($user->email)->toBe('ada@id.test')
        ->and($user->organizationId)->toBe('org_1')
        ->and($user->accessToken)->toBe('at_1')
        ->and($user->refreshToken)->toBe('rt_1');
});

it('rejects a mismatched state (CSRF)', function (): void {
    session(['cbox-id-client.state' => 'st_1']);
    fakeCbox();

    $request = Request::create('https://app.test/callback', 'GET', ['state' => 'forged', 'code' => 'code_1']);
    app(IdentityClient::class)->authenticate($request);
})->throws(InvalidState::class);

it('rejects an id_token with the wrong nonce (replay)', function (): void {
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'nonce_1']);
    fakeCbox(idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'DIFFERENT', 'iat' => time(), 'exp' => time() + 900,
    ]));

    $request = Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']);
    app(IdentityClient::class)->authenticate($request);
})->throws(AuthenticationFailed::class);

it('rejects an id_token from the wrong issuer', function (): void {
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'ver_1', 'cbox-id-client.nonce' => 'n']);
    fakeCbox(idToken([
        'iss' => 'https://evil.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'n', 'iat' => time(), 'exp' => time() + 900,
    ]));

    $request = Request::create('https://app.test/callback', 'GET', ['state' => 'st_1', 'code' => 'code_1']);
    app(IdentityClient::class)->authenticate($request);
})->throws(AuthenticationFailed::class);

it('builds a hosted profile URL with a return link', function (): void {
    fakeCbox();

    $url = app(IdentityClient::class)->profileUrl('https://app.test/account');

    expect($url)->toBe('https://id.test/settings?return_to='.urlencode('https://app.test/account'));
});

// Cbox ID validates post_logout_redirect_uri against the requesting client's
// registered allow-list, so a logout URL without client_id can never redirect —
// it strands the user on a bare "signed out" page.
it('always carries client_id on the RP-initiated logout URL', function (): void {
    fakeCbox();

    $url = app(IdentityClient::class)->logoutUrl('https://app.test/bye');

    expect($url)->toBeString();

    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

    expect(strtok((string) $url, '?'))->toBe('https://id.test/oauth/logout')
        ->and($query['client_id'])->toBe('client_1')
        ->and($query['post_logout_redirect_uri'])->toBe('https://app.test/bye')
        ->and($query)->not->toHaveKey('id_token_hint');

    parse_str((string) parse_url((string) app(IdentityClient::class)->logoutUrl(), PHP_URL_QUERY), $bare);

    expect($bare['client_id'])->toBe('client_1')
        ->and($bare)->not->toHaveKey('post_logout_redirect_uri');
});

it('passes an id_token_hint on the logout URL when one is supplied', function (): void {
    fakeCbox();

    $url = app(IdentityClient::class)->logoutUrl('https://app.test/bye', 'header.payload.sig');

    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

    expect($query['id_token_hint'])->toBe('header.payload.sig')
        ->and($query['client_id'])->toBe('client_1');
});

it('mints a machine (client-credentials) token', function (): void {
    fakeCbox();

    expect(app(IdentityClient::class)->machineToken(['api.read']))->toBe('at_1');
});

it('revokes a token with confidential-client auth and the type hint (RFC 7009)', function (): void {
    fakeCbox();

    app(IdentityClient::class)->revoke('rt_1', 'refresh_token');

    Http::assertSent(function ($request): bool {
        if ($request->url() !== 'https://id.test/oauth/revoke') {
            return false;
        }

        return $request['token'] === 'rt_1'
            && $request['token_type_hint'] === 'refresh_token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('client_1:secret_1'));
    });
});

it('omits token_type_hint when none is given', function (): void {
    fakeCbox();

    app(IdentityClient::class)->revoke('at_1');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://id.test/oauth/revoke'
        && ! array_key_exists('token_type_hint', (array) $request->data()));
});

it('fails a rejected revocation', function (): void {
    // Registered first so it wins over fakeCbox()'s 200 for the same URL — stubs are
    // matched in registration order.
    Http::fake(['*/oauth/revoke' => Http::response(['error' => 'invalid_client'], 401)]);
    fakeCbox();

    app(IdentityClient::class)->revoke('rt_1');
})->throws(AuthenticationFailed::class);

it('verifies an id_token whose JWKS key omits the optional alg member', function (): void {
    // RFC 7517 §4.4 makes `alg` optional; verification must supply its own default
    // rather than depend on the instance emitting it on every key.
    fakeCbox(idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
    ]), rsaKeypair('test-1', declareAlg: false)['jwks']);

    expect(app(IdentityClient::class)->authenticate(loginCallback())->id)->toBe('user-1');
});

it('refetches the JWKS when the instance rotates its signing key mid-TTL', function (): void {
    $rotated = false;
    $claims = ['iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'nonce' => 'nonce_1'];

    fakeCbox(
        idToken: function () use (&$rotated, $claims): string {
            $claims += ['iat' => time(), 'exp' => time() + 900];

            return idToken($claims, $rotated ? 'test-2' : 'test-1');
        },
        // Bound by reference: an arrow function would capture $rotated by value and
        // keep serving the pre-rotation key set.
        jwks: function () use (&$rotated) {
            return Http::response(rsaKeypair($rotated ? 'test-2' : 'test-1')['jwks']);
        },
    );

    // Warm the JWKS cache with the pre-rotation key set.
    expect(app(IdentityClient::class)->authenticate(loginCallback())->id)->toBe('user-1');
    $fetchesBefore = jwksRequestCount();

    $rotated = true;

    // Without the kid-miss refetch this throws "kid invalid" until the TTL lapses.
    expect(app(IdentityClient::class)->authenticate(loginCallback())->id)->toBe('user-1');
    expect(jwksRequestCount())->toBe($fetchesBefore + 1);
});

/**
 * An `openid` request whose response carries no id_token must fail, not fall back.
 *
 * It used to fall back. `is_string($idToken) ? verify() : []` meant a response without one
 * skipped verification entirely and took the subject from UserInfo — the login succeeded,
 * the signature was never checked, and the nonce pulled from the session was never used.
 * An OIDC login degraded into an OAuth one with nothing to show for it.
 *
 * Found while chasing a different bug: three attempts to test outage behaviour all passed
 * without ever fetching a JWKS, because `fakeCbox()` with no idToken argument returns
 * `id_token => null` and the client was happy to proceed. The vacuous test and the missing
 * guard had the same root.
 */
it('refuses an OpenID Connect login whose response carries no id_token', function (): void {
    fakeCbox(idToken: null);

    expect(fn () => app(IdentityClient::class)->authenticate(loginCallback()))
        ->toThrow(AuthenticationFailed::class);
});

/**
 * THE OUTAGE PROMISE, with an id_token so the verification path actually runs.
 *
 * The control-plane argument is that a relying party does not phone home per request:
 * tokens verify locally against a cached JWKS, so the issuer being unreachable is invisible
 * to traffic that is already authenticated. This pins where that stops being true — the
 * cache TTL — by taking the issuer away and clearing the cache under it.
 *
 * `Http::fake()` ACCUMULATES stubs and the first match wins, so a second `fake()` call
 * cannot make an endpoint start failing. The flag flipped by reference inside the original
 * closure is the only way, and it is the idiom the key-rotation test above already uses.
 */
it('stops verifying once the JWKS cache lapses while the issuer is unreachable', function (): void {
    $down = false;

    fakeCbox(
        idToken: fn (): string => idToken([
            'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
            'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
        ]),
        // BY REFERENCE. An arrow function captures `$down` by value and keeps serving
        // good keys however the flag is flipped — the trap the rotation test above warns
        // about, and the one that made three earlier attempts at this test pass without
        // ever reaching an outage.
        jwks: function () use (&$down) {
            return $down ? Http::response('', 503) : Http::response(rsaKeypair()['jwks']);
        },
    );

    // Warm the cache, and prove the issuer being reachable is not what carries the login.
    expect(app(IdentityClient::class)->authenticate(loginCallback())->id)->toBe('user-1');

    // Inside the TTL the issuer can vanish and nothing notices: no fetch is attempted.
    $down = true;
    $before = jwksRequestCount();

    expect(app(IdentityClient::class)->authenticate(loginCallback())->id)->toBe('user-1');
    expect(jwksRequestCount())->toBe($before);

    // Past the TTL it is a hard failure. The token is still valid and the cached keys would
    // still verify it — what broke is only the ability to REFILL a cache the client had a
    // good copy of. There is no stale-on-error grace, and this is where a claim about
    // surviving an outage has to stop.
    Cache::flush();

    expect(fn () => app(IdentityClient::class)->authenticate(loginCallback()))
        ->toThrow(ClientConfigurationException::class);
});

/**
 * USERINFO MUST NOT BE ABLE TO REPLACE THE VERIFIED IDENTITY.
 *
 * OIDC Core §5.3.2: "The sub Claim in the UserInfo Response MUST be verified to exactly
 * match the sub Claim in the ID Token; if they do not match, the UserInfo Response values
 * MUST NOT be used."
 *
 * `array_merge($claims, $this->userinfo(...))` put UserInfo SECOND, so its `sub` overwrote
 * the one that arrived inside a signature — and `$sub` was read after the merge. The whole
 * point of verifying the id_token is that its claims are bound to a key; an unsigned,
 * bearer-authenticated response overriding them gives that away. The refusal message even
 * said "The verified token carried no subject", which by then was not what had happened.
 */
it('refuses a UserInfo response whose subject differs from the verified id_token', function (): void {
    // ONE fake, not fakeCbox() plus an override: `Http::fake()` accumulates stubs and the
    // first match wins, so a later call cannot change an endpoint fakeCbox() already
    // stubbed. (That trap produced four vacuous green tests earlier in this session.)
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'token_endpoint' => 'https://id.test/oauth/token',
            'userinfo_endpoint' => 'https://id.test/oauth/userinfo',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/.well-known/jwks.json' => Http::response(rsaKeypair()['jwks']),
        '*/oauth/token' => Http::response([
            'access_token' => 'at_1',
            'id_token' => idToken([
                'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
                'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
            ]),
            'expires_in' => 900,
        ]),
        // The signature says user-1; UserInfo answers for somebody else entirely.
        '*/oauth/userinfo' => Http::response(['sub' => 'user-2', 'email' => 'mallory@id.test']),
    ]);

    expect(fn () => app(IdentityClient::class)->authenticate(loginCallback()))
        ->toThrow(AuthenticationFailed::class);
})->group('security');

it('refetches at most once per cooldown when the kid is unknown', function (): void {
    // A kid the instance never advertised: refetch once, then back off, so a bogus
    // kid cannot turn every login into a JWKS request.
    fakeCbox(fn (): string => idToken([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1',
        'nonce' => 'nonce_1', 'iat' => time(), 'exp' => time() + 900,
    ], 'no-such-key'));

    foreach (range(1, 3) as $ignored) {
        expect(fn () => app(IdentityClient::class)->authenticate(loginCallback()))
            ->toThrow(AuthenticationFailed::class);
    }

    expect(jwksRequestCount())->toBe(2); // the initial fetch plus one refetch
});

it('verifies a good webhook signature and rejects bad or stale ones', function (): void {
    $client = app(IdentityClient::class);
    $body = '{"event":"user.created"}';
    $secret = 's3cr3t';
    $ts = (string) time();
    $good = 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$body, $secret);

    expect($client->verifyWebhook($body, $good, $secret))->toBeTrue()
        ->and($client->verifyWebhook($body, 't='.$ts.',v1=deadbeef', $secret))->toBeFalse()
        ->and($client->verifyWebhook($body, null, $secret))->toBeFalse();

    // Stale timestamp (outside tolerance) is refused even with a correct HMAC.
    $old = (string) (time() - 4000);
    $stale = 't='.$old.',v1='.hash_hmac('sha256', $old.'.'.$body, $secret);
    expect($client->verifyWebhook($body, $stale, $secret))->toBeFalse();
});

it('reports email verification from the claim, defaulting to false', function (): void {
    $verified = new CboxUser(
        id: 'sub_1', email: 'a@b.test', name: 'A', organizationId: null,
        claims: ['email_verified' => true], accessToken: 'at', refreshToken: null, idToken: 'it', expiresIn: 3600,
    );
    $unverified = new CboxUser(
        id: 'sub_2', email: 'c@d.test', name: 'C', organizationId: null,
        claims: [], accessToken: 'at', refreshToken: null, idToken: 'it', expiresIn: 3600,
    );

    expect($verified->emailVerified())->toBeTrue()
        ->and($unverified->emailVerified())->toBeFalse();
});
