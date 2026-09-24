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

/**
 * The checksum is a cross-SDK contract: Cbox ID, id-js, id-python and id-go all hash the
 * same canonical form, and the fixture is copied verbatim from laravel-id's
 * tests/Fixtures/AccessControl/manifest_hash.json.
 */
dataset('manifest hash cases', function (): array {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/Fixtures/manifest_hash.json'), true);

    $cases = [];
    foreach ($fixture['cases'] as $case) {
        $cases[$case['name']] = [$case];
    }

    return $cases;
});

it('computes the checksum and version Cbox ID computes', function (array $case): void {
    config([
        'cbox-id-client.authz.permissions' => $case['permissions'],
        'cbox-id-client.authz.roles' => $case['roles'],
    ]);
    $this->app->forgetInstance(ManifestPublisher::class);

    $publisher = app(ManifestPublisher::class);

    expect($publisher->checksum())->toBe($case['sha256'])
        ->and(hash('sha256', $case['canonical_json']))->toBe($case['sha256'])
        ->and($publisher->manifest()['version'])->toBe($case['version']);
})->with('manifest hash cases');

it('hashes a manifest the same whatever order the config lists it in', function (): void {
    config([
        'cbox-id-client.authz.permissions' => [['key' => 'b:read'], ['key' => 'a:read']],
        'cbox-id-client.authz.roles' => [['key' => 'z', 'name' => 'Z', 'permissions' => ['b:read', 'a:read']], ['key' => 'y', 'name' => 'Y']],
    ]);
    $this->app->forgetInstance(ManifestPublisher::class);
    $first = app(ManifestPublisher::class)->checksum();

    config([
        'cbox-id-client.authz.permissions' => [['key' => 'a:read'], ['key' => 'b:read']],
        'cbox-id-client.authz.roles' => [['key' => 'y', 'name' => 'Y'], ['key' => 'z', 'name' => 'Z', 'permissions' => ['a:read', 'b:read']]],
    ]);
    $this->app->forgetInstance(ManifestPublisher::class);

    expect(app(ManifestPublisher::class)->checksum())->toBe($first);
});

it('marks only a staff-only role, so a manifest without one hashes as it always has', function (): void {
    $roles = [['key' => 'viewer', 'name' => 'Viewer', 'permissions' => ['invoices:read']]];

    config(['cbox-id-client.authz.roles' => $roles]);
    $this->app->forgetInstance(ManifestPublisher::class);
    $plain = app(ManifestPublisher::class)->checksum();

    config(['cbox-id-client.authz.roles' => [$roles[0] + ['tenant_assignable' => true]]]);
    $this->app->forgetInstance(ManifestPublisher::class);
    expect(app(ManifestPublisher::class)->checksum())->toBe($plain);

    config(['cbox-id-client.authz.roles' => [$roles[0] + ['tenant_assignable' => false]]]);
    $this->app->forgetInstance(ManifestPublisher::class);
    expect(app(ManifestPublisher::class)->checksum())->not->toBe($plain);
});

it('sends a staff-only role to Cbox ID as declared', function (): void {
    config(['cbox-id-client.authz.roles' => [['key' => 'support', 'name' => 'Support', 'permissions' => ['invoices:read'], 'tenant_assignable' => false]]]);
    $this->app->forgetInstance(ManifestPublisher::class);

    expect(app(ManifestPublisher::class)->manifest()['roles'][0]['tenant_assignable'])->toBeFalse();
});

it('refuses a staff marker that is not a boolean, before it can widen access', function (): void {
    config(['cbox-id-client.authz.roles' => [['key' => 'support', 'name' => 'Support', 'tenant_assignable' => 'false']]]);
    $this->app->forgetInstance(ManifestPublisher::class);

    expect(fn () => app(ManifestPublisher::class)->manifest())
        ->toThrow(ClientConfigurationException::class, 'tenant_assignable must be true or false');
});
