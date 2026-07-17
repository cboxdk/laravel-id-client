<?php

declare(strict_types=1);

use Cbox\Id\Client\Authz\ManifestPublisher;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.authz.permissions' => [['key' => 'invoices:read', 'description' => 'View invoices']],
        'cbox-id-client.authz.roles' => [['key' => 'viewer', 'name' => 'Viewer', 'permissions' => ['invoices:read']]],
    ]);
    Cache::flush();
});

it('builds a manifest from config with a content-derived version', function (): void {
    $manifest = app(ManifestPublisher::class)->manifest();

    expect($manifest['roles'])->toHaveCount(1)
        ->and($manifest['permissions'])->toHaveCount(1)
        ->and($manifest['version'])->toBeString()->not->toBe('');
});

it('publishes the manifest with an apps.manifest client-credentials token', function (): void {
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response(['issuer' => 'https://id.test', 'token_endpoint' => 'https://id.test/oauth/token']),
        '*/oauth/token' => Http::response(['access_token' => 'mtoken', 'expires_in' => 900]),
        '*/api/v1/apps/manifest' => Http::response(['unchanged' => false, 'roles_declared' => 1, 'permissions_declared' => 1]),
    ]);

    $result = app(ManifestPublisher::class)->publish();

    expect($result['roles_declared'])->toBe(1);

    // Minted a client-credentials token scoped to apps.manifest…
    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token')
        && $request['grant_type'] === 'client_credentials'
        && $request['scope'] === 'apps.manifest');

    // …then POSTed the manifest with it, carrying the declared role.
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/apps/manifest')
        && $request->hasHeader('Authorization', 'Bearer mtoken')
        && $request['roles'][0]['key'] === 'viewer');
});

it('fails clearly when the push is rejected', function (): void {
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response(['issuer' => 'https://id.test', 'token_endpoint' => 'https://id.test/oauth/token']),
        '*/oauth/token' => Http::response(['access_token' => 'mtoken']),
        '*/api/v1/apps/manifest' => Http::response(['error' => 'insufficient_scope'], 403),
    ]);

    expect(fn () => app(ManifestPublisher::class)->publish())
        ->toThrow(ClientConfigurationException::class);
});
