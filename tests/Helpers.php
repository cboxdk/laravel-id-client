<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/*
 * Shared test doubles for anything that verifies a Cbox ID token.
 *
 * These lived inside the login suite until the access-token verifier needed the same
 * keys and the same JWKS. Two copies would have been two cryptographies: the day one
 * grew a case the other did not, the suites would have disagreed about what a valid
 * token is while both stayed green.
 */

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

/**
 * A token as Cbox ID's issuer actually mints one — the claim names are taken from
 * `JwtTokenIssuer`, not invented here.
 *
 * @param  array<string, mixed>  $overrides
 */
function accessToken(array $overrides = []): string
{
    return idToken(array_replace([
        'iss' => 'https://id.test',
        'sub' => 'user_01',
        'client_id' => 'client_01',
        'jti' => 'tok_01',
        'scope' => 'tax.quote tax.assess',
        'org' => 'org_01',
        'org_name' => 'Acme A/S',
        'aud' => 'https://api.cboxtax.com',
        'iat' => time(),
        'exp' => time() + 300,
    ], $overrides));
}
