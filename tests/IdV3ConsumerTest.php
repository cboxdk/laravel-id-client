<?php

declare(strict_types=1);

use Cbox\Id\Client\AppCatalog\CatalogPayload;
use Cbox\Id\Client\Authz\ManifestPublisher;
use Cbox\Id\Client\ClientServiceProvider;
use Cbox\Id\Client\Contracts\ServiceConnections;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Facades\CboxIdApi;
use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Management\ManagementClients;
use Cbox\Id\Client\Management\OrganizationClient;
use Cbox\Id\Client\Management\WorkspaceClient;
use Cbox\Id\Client\PersonalAppCatalog;
use Cbox\Id\Client\Support\ApiUrl;
use Cbox\Id\Client\Support\PublicId;
use Cbox\Id\Client\Support\Scopes;
use Cbox\Id\Client\ValueObjects\ResourceAuthorizationResult;
use Cbox\Id\Client\ValueObjects\ServiceConnection;
use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Cbox\Id\Client\Webhooks\EventNames;
use Cbox\Id\Client\Webhooks\EventType;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
 * Cbox ID v3, from the consumer's side: everything here is additive. Without
 * CBOX_ID_API_URL the SDK talks to `{issuer}/api/v1` exactly as before; with it, to
 * `{api_url}/v1`. Old and new names (event types, scopes, the organization key, ids with
 * and without their prefix) are read alike for one release.
 */

const V3_ULID = '01j9z3k4m5n6p7q8r9s0t1v2w3';

function v3Deliver(array $envelope, string $path = '/cbox-id/webhooks'): TestResponse
{
    $body = (string) json_encode($envelope);
    $ts = time();

    return test()->call('POST', $path, [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$body, 'whsec_test'),
        'HTTP_X_CBOX_TIMESTAMP' => (string) $ts,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

function v3Catalog(array $body): Response
{
    return new Response(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR)));
}

// -- The API base ---------------------------------------------------------------------

it('keeps {issuer}/api/v1 until CBOX_ID_API_URL is set', function (): void {
    config(['cbox-id-client.api_url' => null]);

    $api = ApiUrl::for('https://acme.test/');

    expect($api->v1)->toBeFalse()
        ->and($api->base)->toBe('https://acme.test/api/v1')
        ->and($api->to('/pipe-leases', '/vault/pipes/github/token'))->toBe('https://acme.test/api/v1/vault/pipes/github/token')
        ->and($api->to('/me/apps'))->toBe('https://acme.test/api/v1/me/apps');
});

it('takes the /v1 paths on CBOX_ID_API_URL, with or without its version', function (string $configured): void {
    config(['cbox-id-client.api_url' => $configured]);

    $api = ApiUrl::for('https://acme.test');

    expect($api->v1)->toBeTrue()
        ->and($api->base)->toBe('https://api.cboxid.com/v1')
        ->and($api->to('/pipe-leases', '/vault/pipes/github/token'))->toBe('https://api.cboxid.com/v1/pipe-leases');
})->with(['https://api.cboxid.com', 'https://api.cboxid.com/', 'https://api.cboxid.com/v1']);

// -- Manifest push, pipe leases, the machine catalog, service connections ----------------

it('pushes the manifest to the /v1 API when it is configured', function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.api_url' => 'https://api.id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.authz.roles' => [['key' => 'viewer', 'name' => 'Viewer', 'permissions' => []]],
    ]);
    Cache::flush();
    $publisher = app(ManifestPublisher::class);
    Http::fake([
        '*/.well-known/openid-configuration' => Http::response(['issuer' => 'https://id.test', 'token_endpoint' => 'https://id.test/oauth/token']),
        'https://id.test/oauth/token' => Http::response(['access_token' => 'mtoken', 'expires_in' => 900]),
        'https://api.id.test/v1/apps/manifest' => Http::response(['client_id' => 'client_1', 'version' => $publisher->manifest()['version'], 'checksum' => $publisher->checksum(), 'unchanged' => false, 'roles_declared' => 1, 'permissions_declared' => 0]),
    ]);

    expect($publisher->publish()['roles_declared'])->toBe(1);
    Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://api.id.test/v1/apps/manifest');
    Http::assertNotSent(fn (HttpRequest $r): bool => str_contains($r->url(), '/api/v1/'));
});

it('leases a pipe token from /v1/pipe-leases, provider in the body', function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.api_url' => 'https://api.id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    app()->forgetInstance(IdentityClient::class);
    Cache::flush();
    Http::swap(new Factory);
    Http::fake([
        'https://api.id.test/v1/pipe-leases' => Http::response(['access_token' => 'gho_abc', 'token_type' => 'Bearer', 'provider' => 'github', 'scopes' => []]),
    ]);

    $token = app(IdentityClient::class)->leasePipeToken('github', 'sync', accessToken: 'person-token');

    expect($token->accessToken)->toBe('gho_abc');
    Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://api.id.test/v1/pipe-leases'
        && $r->data() === ['provider' => 'github', 'purpose' => 'sync']);
});

it('keeps a person\'s catalog grant on the origin it was issued for', function (): void {
    config(['cbox-id-client.api_url' => 'https://api.id.test']);
    Http::fake(['*' => Http::response(['data' => [], 'meta' => ['revision' => str_repeat('a', 64), 'refresh_after' => 60]])]);
    $grant = new ResourceAuthorizationResult('grant', 'usr_'.V3_ULID, V3_ULID, 'https://id.test/mcp', ['id:my-apps:read'], time() + 600, []);

    (new PersonalAppCatalog(app(Factory::class), 'https://id.test'))->read($grant, V3_ULID, 'org_'.strtoupper(V3_ULID));

    Http::assertSent(fn (HttpRequest $r): bool => str_starts_with($r->url(), 'https://id.test/api/v1/me/apps'));
});

describe('service connections', function (): void {
    beforeEach(function (): void {
        config(['cbox-id-client.issuer' => 'https://id.example.test', 'cbox-id-client.client_id' => 'signals']);
        $identity = Mockery::mock(IdentityClient::class);
        $identity->shouldReceive('machineToken')->with(['service_connections.lease'])->andReturn('machine-token');
        app()->instance(IdentityClient::class, $identity);
        app()->forgetInstance(ServiceConnections::class);
        $this->connection = new ServiceConnection(V3_ULID, V3_ULID, 'id_events', 'signals', 'https://id.example.test/feed', ['events:read']);
        $this->status = ['active' => true, 'id' => 'svc_'.V3_ULID, 'organization_id' => 'org_'.V3_ULID, 'service' => 'id_events',
            'consumer_client_id' => 'signals', 'audience' => 'https://id.example.test/feed', 'scopes' => ['events:read'], 'revoked_at' => null];
        Http::preventStrayRequests();
    });

    it('asks /status on the issuer without CBOX_ID_API_URL', function (): void {
        Http::fake(['https://id.example.test/api/v1/service-connections/'.V3_ULID.'/status' => Http::response($this->status)]);

        expect(app(ServiceConnections::class)->status($this->connection))->toBeTrue();
    });

    it('asks /inspect on the /v1 API, and reads prefixed ids as the raw ones it holds', function (): void {
        config(['cbox-id-client.api_url' => 'https://api.example.test']);
        Http::fake(['https://api.example.test/v1/service-connections/'.V3_ULID.'/inspect' => Http::response($this->status)]);

        expect(app(ServiceConnections::class)->status($this->connection))->toBeTrue();
        Http::assertSent(fn (HttpRequest $r): bool => $r->method() === 'POST' && $r->hasHeader('Authorization', 'Bearer machine-token'));
    });

    it('creates with PUT and leases from /lease on the /v1 API', function (): void {
        config(['cbox-id-client.api_url' => 'https://api.example.test']);
        Http::fake([
            'https://api.example.test/v1/service-connections/id_events' => Http::response([...$this->status, 'organization_id' => 'org_'.V3_ULID]),
            'https://api.example.test/v1/service-connections/'.V3_ULID.'/lease' => Http::response(['access_token' => 'provider-token', 'token_type' => 'Bearer', 'expires_in' => 300, 'scope' => 'events:read', 'audience' => 'https://id.example.test/feed']),
        ]);

        $connection = app(ServiceConnections::class)->connect('human-token', V3_ULID, 'id_events', 'https://id.example.test/feed');
        expect(app(ServiceConnections::class)->lease($this->connection)->accessToken)->toBe('provider-token');

        expect($connection->organizationId)->toBe(V3_ULID);
        Http::assertSent(fn (HttpRequest $r): bool => $r->method() === 'PUT' && str_ends_with($r->url(), '/v1/service-connections/id_events'));
    });
});

// -- The catalog envelope ---------------------------------------------------------------

it('reads the /v1 list envelope with its unavailable list and passes the new fields through', function (): void {
    $catalog = CatalogPayload::parse(v3Catalog([
        'object' => 'list',
        'data' => [['key' => 'work', 'name' => 'Cbox Work', 'purpose' => 'Time and invoices', 'hue' => 'amber', 'launch_url' => 'https://work.test/', 'available' => true]],
        'unavailable' => [['key' => 'signals', 'name' => 'Cbox Signals', 'hue' => 'violet', 'available' => false, 'admission_url' => 'https://signals.test/signup?organization=org_'.V3_ULID]],
        'revision' => str_repeat('b', 64),
        'refresh_after' => 60,
    ]), 'https://id.test');

    expect($catalog['revision'])->toBe(str_repeat('b', 64))
        ->and($catalog['refresh_after'])->toBe(60)
        ->and($catalog['apps'][0])->toMatchArray(['key' => 'work', 'purpose' => 'Time and invoices', 'hue' => 'amber', 'enabled' => true, 'admission_url' => null])
        ->and($catalog['apps'][1])->toMatchArray(['key' => 'signals', 'hue' => 'violet', 'enabled' => false, 'launch_url' => null,
            'admission_url' => 'https://signals.test/signup?organization=org_'.V3_ULID]);
});

it('still reads today\'s envelope, unavailable list included', function (): void {
    $catalog = CatalogPayload::parse(v3Catalog([
        'data' => [['key' => 'work', 'name' => 'Work', 'launch_url' => 'https://work.test/']],
        'unavailable' => [['key' => 'signals', 'name' => 'Signals', 'admission_url' => 'https://signals.test/signup']],
        'meta' => ['revision' => str_repeat('a', 64), 'refresh_after' => 30],
    ]), 'https://id.test');

    expect($catalog['refresh_after'])->toBe(30)
        ->and(array_column($catalog['apps'], 'enabled', 'key'))->toBe(['work' => true, 'signals' => false]);
});

// -- Webhooks -----------------------------------------------------------------------------

describe('webhook event names', function (): void {
    beforeEach(function (): void {
        config(['cbox-id-client.webhooks.secret' => 'whsec_test']);
    });

    it('runs the same handler for an event\'s old name and its id.* name', function (string $registered, string $delivered): void {
        $received = [];
        CboxIdWebhooks::on($registered, function (WebhookEvent $event) use (&$received): void {
            $received[] = $event->type;
        });

        // The user-properties events are validated on receipt, under either name.
        $data = match (EventType::fromName($delivered)) {
            EventType::UserPreferencesUpdated, EventType::UserMetadataUpdated => [
                'event_id' => 'evt_1', 'user_id' => 'usr_1', 'occurred_at' => '2026-11-11T10:00:00Z', 'version' => 1,
                'namespace' => EventType::fromName($delivered) === EventType::UserPreferencesUpdated ? 'preferences' : 'profile',
                'changed_keys' => ['theme'], 'client_id' => null,
            ],
            default => [],
        };

        v3Deliver(['type' => $delivered, 'data' => $data])->assertOk()->assertJson(['queued' => true]);

        expect($received)->toBe([$delivered]);
    })->with(function (): array {
        $pairs = [];

        foreach (EventType::cases() as $type) {
            $pairs[$type->value.' handler, '.$type->idName().' delivered'] = [$type->value, $type->idName()];
            $pairs[$type->idName().' handler, '.$type->value.' delivered'] = [$type->idName(), $type->value];
        }

        return $pairs;
    });

    it('names user.login id.user.signed_in and every other event id.<old name>', function (): void {
        expect(EventType::UserLogin->idName())->toBe('id.user.signed_in')
            ->and(EventType::OrganizationMemberAdded->idName())->toBe('id.organization.member_added')
            ->and(EventType::fromName('id.user.signed_in'))->toBe(EventType::UserLogin)
            ->and(EventType::fromName('id.organization.member_added'))->toBe(EventType::OrganizationMemberAdded)
            ->and(EventNames::legacy('id.plugin.thing_happened'))->toBe('plugin.thing_happened')
            ->and(EventNames::current(EventType::WILDCARD))->toBe(EventType::WILDCARD);
    });

    it('answers is() and eventType() alike for both names', function (): void {
        $event = new WebhookEvent('id.organization.member_added');

        expect($event->is(EventType::OrganizationMemberAdded))->toBeTrue()
            ->and($event->is('organization.member_added'))->toBeTrue()
            ->and($event->is('organization.member_removed'))->toBeFalse()
            ->and($event->eventType())->toBe(EventType::OrganizationMemberAdded);
    });

    it('runs a handler registered under both names once', function (): void {
        $runs = 0;
        $handler = function () use (&$runs): void {
            $runs++;
        };
        CboxIdWebhooks::on('role.assigned', $handler);
        CboxIdWebhooks::on('id.role.assigned', $handler);

        v3Deliver(['type' => 'id.role.assigned', 'data' => []])->assertOk();

        expect($runs)->toBe(1);
    });

    it('dispatches an old name to its handlers exactly as 0.19.3 did', function (): void {
        $runs = 0;
        $handler = function () use (&$runs): void {
            $runs++;
        };
        CboxIdWebhooks::on('role.assigned', $handler);
        CboxIdWebhooks::on('role.assigned', $handler);

        v3Deliver(['type' => 'role.assigned', 'data' => []])->assertOk();

        expect($runs)->toBe(2);
    });

    it('gives the raw organization id when the payload carries a prefixed one', function (): void {
        $received = null;
        CboxIdWebhooks::on('*', function (WebhookEvent $event) use (&$received): void {
            $received = $event;
        });

        v3Deliver(['type' => 'id.membership.created', 'data' => ['organization_id' => 'org_'.strtoupper(V3_ULID), 'user_id' => 'usr_'.V3_ULID]])->assertOk();

        expect($received?->organizationId)->toBe(V3_ULID)
            ->and($received?->id('user_id'))->toBe(V3_ULID)
            ->and($received?->string('user_id'))->toBe('usr_'.V3_ULID);
    });

    it('receives at /webhooks/cbox-id next to /cbox-id/webhooks', function (string $path): void {
        v3Deliver(['type' => 'user.created', 'data' => []], $path)->assertOk();
    })->with([ClientServiceProvider::WEBHOOK_PATH, ClientServiceProvider::LEGACY_WEBHOOK_PATH]);
});

// -- Organization key ---------------------------------------------------------------------

describe('the organization plane', function (): void {
    beforeEach(function (): void {
        config(['cbox-id-client.management.root_url' => 'https://root.test']);
        app()->forgetInstance(ManagementClients::class);
    });

    it('reads CBOX_ID_ORGANIZATION_KEY first', function (): void {
        config(['cbox-id-client.management.organization_key' => 'cbid_org_new', 'cbox-id-client.management.workspace_key' => 'cbid_ws_old']);
        $seen = fakeManagement(Http::response(['data' => []]));

        CboxIdApi::organization()->request('GET', '/workspace');

        expect($seen[0]->header('Authorization'))->toBe(['Bearer cbid_org_new']);
    });

    it('falls back to CBOX_ID_WORKSPACE_KEY, and workspace() is the same client', function (): void {
        config(['cbox-id-client.management.organization_key' => null, 'cbox-id-client.management.workspace_key' => 'cbid_ws_old']);
        $seen = fakeManagement(Http::response(['data' => []]));

        CboxIdApi::workspace()->request('GET', '/workspace');

        expect($seen[0]->header('Authorization'))->toBe(['Bearer cbid_ws_old'])
            ->and(CboxIdApi::workspace())->toBe(CboxIdApi::organization())
            ->and(app(OrganizationClient::class))->toBe(app(WorkspaceClient::class))
            ->and(app(WorkspaceClient::class))->toBeInstanceOf(OrganizationClient::class)
            ->and(CboxIdApi::workspaceAs('root_token'))->toBeInstanceOf(OrganizationClient::class);
    });

    it('names both variables when neither is set', function (): void {
        config(['cbox-id-client.management.organization_key' => null, 'cbox-id-client.management.workspace_key' => null]);

        expect(fn () => CboxIdApi::organization())->toThrow(NotConfigured::class, 'CBOX_ID_ORGANIZATION_KEY, or the older CBOX_ID_WORKSPACE_KEY');
    });
});

// -- Scopes and ids ------------------------------------------------------------------------

it('reads a scope in either spelling', function (string $held, string $asked): void {
    expect(Scopes::holds([$held], $asked))->toBeTrue();
})->with([
    ['account:apps:read', 'id:my-apps:read'],
    ['id:my-apps:read', 'account:apps:read'],
    ['app_catalog:resolve', 'id:app-catalog-for-user:read'],
    ['id:service-connection-leases:write', 'service_connections.lease'],
    ['keys:write', 'id:organization-keys:write'],
]);

it('does not confuse different scopes', function (): void {
    expect(Scopes::holds(['account:apps:read'], 'id:my-apps:write'))->toBeFalse()
        ->and(Scopes::holds(['vault.manage'], 'token_vault:write'))->toBeFalse()
        ->and(Scopes::holds(['invoices:read'], 'invoices:read'))->toBeTrue();
});

it('checks a verified token\'s scopes in either spelling', function (): void {
    $token = new VerifiedToken('usr_1', 'client_1', null, null, 'https://app.test', ['id:app-manifests:write'], [], 'jti_1', time() + 60, []);

    expect($token->hasScope('apps.manifest'))->toBeTrue()
        ->and($token->hasScope('id:app-manifests:write'))->toBeTrue()
        ->and($token->hasScope('apps:write'))->toBeFalse();
});

it('compares ids with and without their prefix', function (): void {
    expect(PublicId::same('org_'.V3_ULID, V3_ULID))->toBeTrue()
        ->and(PublicId::same('ORG_'.strtoupper(V3_ULID), V3_ULID))->toBeTrue()
        ->and(PublicId::same(V3_ULID, V3_ULID))->toBeTrue()
        ->and(PublicId::same('org_1', 'org_1'))->toBeTrue()
        ->and(PublicId::same('org_1', '1'))->toBeFalse()
        ->and(PublicId::same('org_'.V3_ULID, '01j9z3k4m5n6p7q8r9s0t1v2w4'))->toBeFalse()
        ->and(PublicId::same(null, V3_ULID))->toBeFalse()
        ->and(PublicId::raw('cid_short'))->toBe('cid_short')
        ->and(PublicId::isPrefixed('usr_'.V3_ULID))->toBeTrue();
});
