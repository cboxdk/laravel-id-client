<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\PipeLeaseDenied;
use Cbox\Id\Client\Exceptions\PipeLeaseFailed;
use Cbox\Id\Client\Exceptions\PipeNotConnected;
use Cbox\Id\Client\Exceptions\PipeReauthorizationRequired;
use Cbox\Id\Client\Exceptions\PipeTemporarilyUnavailable;
use Cbox\Id\Client\Facades\CboxId;
use Cbox\Id\Client\IdentityClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pipes: leasing a person's connected-account token, and the typed refusals.
 */
const CONNECT_URL = 'https://id.test/account/connected-services/github/connect';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    Cache::flush();
});

function leaseAnswers(mixed $lease): void
{
    // A fresh factory, so a second fake in one test replaces the first instead of queueing behind it.
    Http::swap(new Factory);
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://id.test',
            'authorization_endpoint' => 'https://id.test/oauth/authorize',
            'token_endpoint' => 'https://id.test/oauth/token',
            'jwks_uri' => 'https://id.test/.well-known/jwks.json',
        ]),
        '*/oauth/token' => Http::response(['access_token' => 'lease-token', 'expires_in' => 3600]),
        '*/api/v1/vault/pipes/*' => $lease,
    ]);
}

function leaseBody(array $overrides = []): array
{
    return $overrides + [
        'access_token' => 'gho_abc',
        'token_type' => 'Bearer',
        'provider' => 'github',
        'user_id' => 'usr_1',
        'connection_id' => 'con_1',
        'scopes' => ['read:user', 'repo'],
        'expires_at' => null,
        'lease_expires_at' => '2026-10-09T14:05:00+00:00',
        'metadata' => [],
    ];
}

it('leases as the app with a vault.lease machine token, naming the person', function (): void {
    leaseAnswers(Http::response(leaseBody()));

    $token = CboxId::leasePipeToken('github', purpose: 'list-repos', userId: 'usr_1');

    expect($token->accessToken)->toBe('gho_abc')
        ->and($token->scopes)->toBe(['read:user', 'repo'])
        ->and($token->expiresAt)->toBeNull()
        ->and($token->leaseExpiresAt?->format(DATE_ATOM))->toBe('2026-10-09T14:05:00+00:00');

    Http::assertSent(fn (HttpRequest $r): bool => str_ends_with($r->url(), '/oauth/token') && $r['scope'] === 'vault.lease');
    Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://id.test/api/v1/vault/pipes/github/token'
        && $r->hasHeader('Authorization', 'Bearer lease-token')
        && $r->data() === ['purpose' => 'list-repos', 'user_id' => 'usr_1']);
});

it('leases with a token issued for the person, leaving user_id out', function (): void {
    leaseAnswers(Http::response(leaseBody(['metadata' => ['instance_url' => 'https://acme.sf.com']])));

    $token = app(IdentityClient::class)->leasePipeToken('salesforce', 'sync', accessToken: 'person-token');

    expect($token->metadata)->toBe(['instance_url' => 'https://acme.sf.com']);
    Http::assertNotSent(fn (HttpRequest $r): bool => str_ends_with($r->url(), '/oauth/token'));
    Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), '/vault/pipes/')
        && $r->hasHeader('Authorization', 'Bearer person-token')
        && $r->data() === ['purpose' => 'sync']);
});

it('surfaces connect_url when the person has not connected, ready to bring them back', function (): void {
    leaseAnswers(Http::response(['error' => 'not_connected', 'message' => 'Not connected.', 'connect_url' => CONNECT_URL], 404));

    try {
        CboxId::leasePipeToken('github', 'p', 'usr_1');
        $this->fail('Expected a refusal.');
    } catch (PipeNotConnected $e) {
        expect($e)->toBeInstanceOf(PipeLeaseFailed::class)
            ->and($e->status)->toBe(404)
            ->and($e->error)->toBe('not_connected')
            ->and($e->connectUrl)->toBe(CONNECT_URL)
            ->and($e->connectUrlWith('client_1', 'https://app.test/s'))
            ->toBe(CONNECT_URL.'?client_id=client_1&return_to=https%3A%2F%2Fapp.test%2Fs');
    }
});

it('types each refusal the lease endpoint answers', function (int $status, array $body, string $class, array $headers = []): void {
    leaseAnswers(Http::response($body, $status, $headers));

    expect(fn () => CboxId::leasePipeToken('github', 'p'))->toThrow($class);
})->with([
    'reconnect' => [409, ['error' => 'reauthorization_required', 'connect_url' => CONNECT_URL], PipeReauthorizationRequired::class],
    'unavailable' => [503, ['error' => 'temporarily_unavailable'], PipeTemporarilyUnavailable::class, ['Retry-After' => '30']],
    'denied' => [403, ['error' => 'lease_denied'], PipeLeaseDenied::class],
    'missing scope' => [403, ['error' => 'insufficient_scope'], PipeLeaseFailed::class],
    'not json' => [502, [], PipeLeaseFailed::class],
]);

it('carries Retry-After, and no connect URL on a denial', function (): void {
    leaseAnswers(Http::response(['error' => 'temporarily_unavailable'], 503, ['Retry-After' => '30']));

    try {
        CboxId::leasePipeToken('google', 'p');
    } catch (PipeTemporarilyUnavailable $e) {
        expect($e->retryAfter)->toBe(30)->and($e->connectUrlWith('client_1'))->toBeNull();
    }

    leaseAnswers(Http::response(['error' => 'insufficient_scope'], 403));

    try {
        CboxId::leasePipeToken('google', 'p');
    } catch (PipeLeaseFailed $e) {
        expect($e)->not->toBeInstanceOf(PipeLeaseDenied::class)->and($e->error)->toBe('insufficient_scope');
    }
});

it('builds the hosted connect page, preselected to this app', function (): void {
    expect(CboxId::pipeConnectUrl('slack', 'https://app.test/integrations'))
        ->toBe('https://id.test/account/connected-services/slack/connect?client_id=client_1&return_to=https%3A%2F%2Fapp.test%2Fintegrations')
        ->and(CboxId::pipeConnectUrl('notion'))->toBe('https://id.test/account/connected-services/notion/connect?client_id=client_1')
        ->and(CboxId::redirectToPipeConnect('github')->getTargetUrl())->toBe(CONNECT_URL.'?client_id=client_1');
});
