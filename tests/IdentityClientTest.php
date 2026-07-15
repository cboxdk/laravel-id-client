<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\IdentityClient;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * @return array{private: string, jwks: array<string, mixed>}
 */
function rsaKeypair(): array
{
    static $kp = null;

    if ($kp !== null) {
        return $kp;
    }

    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($res, $priv);
    $details = openssl_pkey_get_details($res);

    $b64 = static fn (string $b): string => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    $jwks = ['keys' => [[
        'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'test-1',
        'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e']),
    ]]];

    return $kp = ['private' => $priv, 'jwks' => $jwks];
}

/**
 * @param  array<string, mixed>  $claims
 */
function idToken(array $claims): string
{
    return JWT::encode($claims, rsaKeypair()['private'], 'RS256', 'test-1');
}

function fakeCbox(?string $idToken = null): void
{
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'authorization_endpoint' => 'https://id.test/oauth/authorize',
            'token_endpoint' => 'https://id.test/oauth/token',
            'userinfo_endpoint' => 'https://id.test/oauth/userinfo',
            'introspection_endpoint' => 'https://id.test/oauth/introspect',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/.well-known/jwks.json' => Http::response(rsaKeypair()['jwks']),
        '*/oauth/token' => Http::response(['access_token' => 'at_1', 'id_token' => $idToken, 'refresh_token' => 'rt_1', 'expires_in' => 900]),
        '*/oauth/userinfo' => Http::response(['sub' => 'user-1', 'email' => 'ada@id.test', 'name' => 'Ada', 'org' => 'org_1']),
        '*/oauth/introspect' => Http::response(['active' => true, 'sub' => 'user-1']),
    ]);
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

it('mints a machine (client-credentials) token', function (): void {
    fakeCbox();

    expect(app(IdentityClient::class)->machineToken(['api.read']))->toBe('at_1');
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
