<?php

declare(strict_types=1);

use Cbox\Id\Client\AccessTokenVerifier;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Cbox\Id\Client\Exceptions\CboxIdException;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\InvalidState;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\OrganizationRequired;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Discovery;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/**
 * What an application sees when Cbox ID says no, or is not set up.
 *
 * Every one of these used to reach the application as something else: a callback error
 * without its description, a missing issuer as Guzzle's "URI must include a scheme", a
 * token without an organization as a bare RuntimeException — all of them 500s, and none
 * of them saying what was actually wrong.
 */
beforeEach(function (): void {
    Cache::flush();
});

it('gives everything the SDK throws one base, and keeps it a RuntimeException', function (): void {
    foreach ([
        AuthenticationFailed::because('x'),
        ClientConfigurationException::because('x'),
        NotConfigured::key('issuer'),
        OrganizationRequired::forToken(),
    ] as $exception) {
        expect($exception)->toBeInstanceOf(CboxIdException::class)
            ->and($exception)->toBeInstanceOf(RuntimeException::class);
    }
});

it('carries the error_description of a refused authorization, not only its code', function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => 'client_1', 'cbox-id-client.redirect' => 'https://app.test/cb']);
    session(['cbox-id-client.state' => 'st_1', 'cbox-id-client.verifier' => 'v', 'cbox-id-client.nonce' => 'n']);

    $request = Request::create('https://app.test/cb', 'GET', [
        'state' => 'st_1',
        'error' => 'invalid_scope',
        'error_description' => 'This application is not registered for the requested scope(s): groups',
    ]);

    try {
        app(IdentityClient::class)->authenticate($request);
        $this->fail('A refused authorization must not complete.');
    } catch (AuthenticationFailed $e) {
        expect($e->error)->toBe('invalid_scope')
            ->and($e->errorDescription)->toBe('This application is not registered for the requested scope(s): groups')
            ->and($e->getMessage())->toContain('not registered for the requested scope(s): groups');
    }
});

it('checks the state before believing an error in the callback', function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => 'client_1', 'cbox-id-client.redirect' => 'https://app.test/cb']);
    session(['cbox-id-client.state' => 'st_1']);

    $request = Request::create('https://app.test/cb', 'GET', ['state' => 'forged', 'error' => 'access_denied']);

    expect(fn () => app(IdentityClient::class)->authenticate($request))
        ->toThrow(InvalidState::class, 'The login state did not match');
});

it('names the missing issuer instead of letting Guzzle complain about a scheme', function (): void {
    config(['cbox-id-client.issuer' => null, 'cbox-id-client.client_id' => 'client_1', 'cbox-id-client.redirect' => 'https://app.test/cb']);
    Http::fake();

    try {
        app(IdentityClient::class)->redirect();
        $this->fail('An unconfigured issuer must be refused.');
    } catch (NotConfigured $e) {
        expect($e->key)->toBe('issuer')
            ->and($e->getMessage())->toContain('CBOX_ID_ISSUER')
            ->and($e->getStatusCode())->toBe(503);
    }

    // Refused before any request left the process — no URL was ever built from nothing.
    Http::assertNothingSent();
});

it('answers an unconfigured deployment with a 503 envelope, not a 500', function (): void {
    config(['cbox-id-client.issuer' => '']);
    Route::get('/login', fn () => app(IdentityClient::class)->redirect());

    $this->getJson('/login')
        ->assertStatus(503)
        ->assertJsonPath('error.type', 'service_misconfigured');
});

it('names the config key when a required client setting is empty', function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => '']);
    fakeCbox();

    expect(fn () => app(IdentityClient::class)->redirect())
        ->toThrow(NotConfigured::class, 'cbox-id-client.client_id');
});

it('refuses a tokenless-organization token with a 403 reason, not a 500', function (): void {
    fakeCbox();
    $this->app->instance(AccessTokenVerifier::class, new AccessTokenVerifier(new Discovery('https://id.test', 3600, 10), 'https://id.test', 'https://api.cboxtax.com'));

    Route::middleware('cbox-id.token')->get('/tenant', fn () => ['org' => app(VerifiedToken::class)->organizationOrFail()]);

    $this->getJson('/tenant', ['Authorization' => 'Bearer '.accessToken(['org' => null])])
        ->assertStatus(403)
        ->assertJsonPath('error.type', 'organization_required');

    $this->getJson('/tenant', ['Authorization' => 'Bearer '.accessToken()])
        ->assertOk()
        ->assertJsonPath('org', 'org_01');
});

it('reads the login scopes from CBOX_ID_SCOPES, space- or comma-separated', function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => 'client_1', 'cbox-id-client.redirect' => 'https://app.test/cb']);
    fakeCbox();

    // A published config that passes the raw env string straight through.
    config(['cbox-id-client.scopes' => 'openid profile, email organizations']);
    $this->app->forgetInstance(IdentityClient::class);

    $url = app(IdentityClient::class)->redirect()->getTargetUrl();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('openid profile email organizations');
});
