<?php

declare(strict_types=1);

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Illuminate\Support\Facades\Route;

/**
 * The middleware, through a real route and a real request.
 *
 * What matters here is not that the verifier works — that is proven next door — but
 * what the middleware DOES with each answer. A rejection has to reach the caller as
 * something they can act on: a 401 that says the token expired is answered by
 * fetching a new one, where a 500 is answered by retrying the same dead credential
 * until somebody notices.
 */
beforeEach(function () {
    fakeCbox();

    $this->app->instance(AccessTokenVerifier::class, new AccessTokenVerifier(
        new Discovery('https://id.test', 3600, 10),
        'https://id.test',
        'https://api.cboxtax.com',
    ));

    Route::middleware('cbox-id.token')->get('/protected', fn () => response()->json([
        'org' => app(VerifiedToken::class)->organizationId,
    ]));

    Route::middleware('cbox-id.token:tax.assess')->get('/assess', fn () => response()->json(['ok' => true]));
});

function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

it('lets a verified token through and hands the handler the token itself', function () {
    // Resolved from the container rather than read off the request, so a handler
    // cannot accidentally work with an unverified string — the type only exists on
    // the far side of verification.
    $this->getJson('/protected', bearer(accessToken()))
        ->assertOk()
        ->assertJsonPath('org', 'org_01');
});

it('answers 401 when nothing was presented', function () {
    $response = $this->getJson('/protected');

    $response->assertStatus(401)
        // 'invalid_request', not 'invalid_token': there is no token to call invalid,
        // and a client retrying one it never sent is a confusing place to start.
        ->assertJsonPath('error.type', 'invalid_request');

    expect($response->headers->get('WWW-Authenticate'))->toContain('error="invalid_request"');
});

it('answers 401 with a machine-readable reason when the token is bad', function () {
    $response = $this->getJson('/protected', bearer(accessToken(['iss' => 'https://evil.test'])));

    $response->assertStatus(401)->assertJsonPath('error.type', 'invalid_token');

    // RFC 6750 §3 — a conformant client acts on the header without parsing prose.
    expect($response->headers->get('WWW-Authenticate'))->toContain('Bearer error="invalid_token"');
});

// A valid token missing a scope is a different answer from an invalid one: the
// caller is who they say they are and may not do this. 403 rather than 401, because
// a new token of the same kind will not help.
it('answers 403 and names the scope when one is missing', function () {
    $response = $this->getJson('/assess', bearer(accessToken(['scope' => 'tax.quote'])));

    $response->assertStatus(403)->assertJsonPath('error.type', 'insufficient_scope');

    expect($response->headers->get('WWW-Authenticate'))->toContain('scope="tax.assess"');
});

// THE ONE THIS SUITE MISSED. Every bad-token case above uses the unscoped route, so
// they all passed while the middleware was deciding the error from what the ROUTE
// required rather than from why the token failed — reporting a forged signature on a
// scoped route as insufficient_scope, with a 403 that says "you are who you say you
// are, but may not do this" about a token nobody signed.
it('calls a bad token invalid even on a route that requires scopes', function () {
    $response = $this->getJson('/assess', bearer(accessToken(['exp' => time() - 1, 'iat' => time() - 600])));

    $response->assertStatus(401)->assertJsonPath('error.type', 'invalid_token');

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('error="invalid_token"')
        // Nothing about scopes: the caller's grant is not the problem, and sending
        // them chasing a broader one is the wrong remedy.
        ->not->toContain('scope=');
});

it('lets a token carrying the required scope through', function () {
    $this->getJson('/assess', bearer(accessToken()))->assertOk();
});

it('ignores an Authorization header that is not a bearer', function () {
    $this->getJson('/protected', ['Authorization' => 'Basic '.base64_encode('a:b')])
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'invalid_request');
});

it('treats a bearer prefix with no token as nothing presented', function () {
    $this->getJson('/protected', ['Authorization' => 'Bearer   '])
        ->assertStatus(401)
        ->assertJsonPath('error.type', 'invalid_request');
});

// A verifier message can contain a double quote, and an unbalanced one turns a valid
// challenge header into one a client cannot parse — so the header stays parseable
// even when the reason does not cooperate.
it('keeps the challenge header parseable whatever the reason says', function () {
    $header = $this->getJson('/protected', bearer('not-a-jwt-at-all'))
        ->headers->get('WWW-Authenticate');

    expect(substr_count((string) $header, '"') % 2)->toBe(0);
});
