<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Exceptions\ValidationFailed;
use Cbox\Id\Client\Facades\CboxIdManagement;
use Cbox\Id\Client\Management\Data\ApiChanges;
use Cbox\Id\Client\Management\Data\ApiScope;
use Cbox\Id\Client\Management\Data\NewApi;
use Cbox\Id\Client\Management\Data\NewApp;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\Data\NewSupportSession;
use Cbox\Id\Client\Management\Data\OrganizationChanges;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/**
 * The environment management API client, against the contract's paths and envelopes:
 * `Bearer cbid_env_…`, `{data, meta}` on success, `{error, message}` on failure.
 */
beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://acme.cboxid.test',
        'cbox-id-client.management.key' => 'cbid_env_test',
    ]);
});

function management(): Management
{
    return app(Management::class);
}

/** @return list<array{method: string, path: string, body: array<mixed>}> */
function sentRequests(): array
{
    return Http::recorded()->map(fn (array $pair): array => [
        'method' => $pair[0]->method(),
        'path' => (string) parse_url($pair[0]->url(), PHP_URL_PATH),
        'body' => $pair[0]->data(),
    ])->all();
}

it('authenticates with the environment key against the issuer host', function (): void {
    Http::fake(['*' => Http::response(['data' => ['id' => 'org_1', 'name' => 'Acme']])]);

    management()->organization('org_1');

    Http::assertSent(fn (HttpRequest $r): bool => $r->url() === 'https://acme.cboxid.test/api/v1/organizations/org_1'
        && $r->hasHeader('Authorization', 'Bearer cbid_env_test'));
});

it('creates an organization with its owner, and reads the typed answer', function (): void {
    Http::fake(['*' => Http::response(['data' => ['id' => 'org_1', 'name' => 'Acme', 'slug' => 'acme', 'type' => 'customer', 'status' => 'active', 'parent_id' => null]], 201)]);

    $org = CboxIdManagement::createOrganization(new NewOrganization('Acme', ownerUserId: 'user_1'));

    expect($org->id)->toBe('org_1')->and($org->slug)->toBe('acme')->and($org->status)->toBe('active');
    expect(sentRequests()[0])->toBe(['method' => 'POST', 'path' => '/api/v1/organizations', 'body' => ['name' => 'Acme', 'owner_user_id' => 'user_1']]);
});

it('speaks every endpoint in the contract with the right verb and path', function (): void {
    Http::fake([
        '*/roles' => Http::response(['data' => [['id' => 'role_1', 'key' => 'editor', 'tenant_assignable' => false]]]),
        '*/members/user_1/roles' => Http::response(['data' => [['role_id' => 'role_1', 'key' => 'editor']]]),
        '*/environment-roles/*' => Http::response(['data' => ['role_id' => 'role_1']]),
        '*/blueprint' => Http::response(['data' => ['name' => 'Portal', 'scopes' => ['openid']]]),
        '*/support-sessions' => Http::response(['data' => ['id' => 'sup_1', 'user_id' => 'user_1', 'act' => ['sub' => 'staff_1']]]),
        '*' => Http::response(['data' => ['id' => 'x_1', 'name' => 'n', 'email' => 'e@x.test', 'user_id' => 'user_1', 'role' => 'admin', 'identifier' => 'https://api.test', 'client_id' => 'cid_1'], 'meta' => ['has_more' => false]]),
    ]);

    $m = management();
    $m->organizations(after: 'org_0', limit: 10);
    $m->updateOrganization('org_1', new OrganizationChanges(name: 'Acme Ltd'));
    $m->archiveOrganization('org_1');
    $m->members('org_1');
    $m->addMember('org_1', 'user_1', OrganizationRole::Admin);
    $m->updateMember('org_1', 'user_1', OrganizationRole::Viewer);
    $m->removeMember('org_1', 'user_1');
    $m->transferOwnership('org_1', 'user_2');
    $m->invitations('org_1');
    $m->invite('org_1', new NewInvitation('ada@example.test', OrganizationRole::Member, ['editor'], 'https://app.test/welcome', 'cid_1'));
    $m->revokeInvitation('org_1', 'inv_1');
    $m->resendInvitation('org_1', 'inv_1');
    $roles = $m->memberRoles('org_1', 'user_1');
    $m->assignRole('org_1', 'user_1', 'role_1');
    $m->unassignRole('org_1', 'user_1', 'role_1');
    $all = $m->roles();
    expect($m->hasEnvironmentRole('user_1', 'role_1'))->toBeTrue();
    $m->grantEnvironmentRole('user_1', 'role_1');
    $m->revokeEnvironmentRole('user_1', 'role_1');
    $m->apps();
    $m->createApp(new NewApp('Portal', 'web', ['https://app.test/cb']));
    $blueprint = $m->appBlueprint('cid_1');
    $m->apis();
    $m->createApi(new NewApi('https://api.test', 'API', [new ApiScope('reports:read', 'Read reports', false)], 'cid_1'));
    $m->updateApi('api_1', new ApiChanges(name: 'Reports API'));
    $m->deleteApi('api_1');
    $m->apiKeys('org_1');
    $m->revokeApiKey('key_1');
    $session = $m->startSupportSession(new NewSupportSession('user_1', 'org_1', 'cid_1', 'Ticket #42'));

    expect(array_map(fn (array $r): string => $r['method'].' '.$r['path'], sentRequests()))->toBe([
        'GET /api/v1/organizations',
        'PATCH /api/v1/organizations/org_1',
        'DELETE /api/v1/organizations/org_1',
        'GET /api/v1/organizations/org_1/members',
        'POST /api/v1/organizations/org_1/members',
        'PATCH /api/v1/organizations/org_1/members/user_1',
        'DELETE /api/v1/organizations/org_1/members/user_1',
        'POST /api/v1/organizations/org_1/transfer-ownership',
        'GET /api/v1/organizations/org_1/invitations',
        'POST /api/v1/organizations/org_1/invitations',
        'DELETE /api/v1/organizations/org_1/invitations/inv_1',
        'POST /api/v1/organizations/org_1/invitations/inv_1/resend',
        'GET /api/v1/organizations/org_1/members/user_1/roles',
        'PUT /api/v1/organizations/org_1/members/user_1/roles/role_1',
        'DELETE /api/v1/organizations/org_1/members/user_1/roles/role_1',
        'GET /api/v1/roles',
        'GET /api/v1/users/user_1/environment-roles/role_1',
        'PUT /api/v1/users/user_1/environment-roles/role_1',
        'DELETE /api/v1/users/user_1/environment-roles/role_1',
        'GET /api/v1/apps',
        'POST /api/v1/apps',
        'GET /api/v1/apps/cid_1/blueprint',
        'GET /api/v1/apis',
        'POST /api/v1/apis',
        'PATCH /api/v1/apis/api_1',
        'DELETE /api/v1/apis/api_1',
        'GET /api/v1/organizations/org_1/api-keys',
        'DELETE /api/v1/api-keys/key_1',
        'POST /api/v1/support-sessions',
    ]);

    $sent = sentRequests();
    expect($sent[0]['body'])->toBe(['limit' => 10, 'after' => 'org_0'])
        ->and($sent[4]['body'])->toBe(['user_id' => 'user_1', 'role' => 'admin'])
        ->and($sent[7]['body'])->toBe(['user_id' => 'user_2'])
        ->and($sent[9]['body'])->toBe(['email' => 'ada@example.test', 'role' => 'member', 'roles' => ['editor'], 'return_to' => 'https://app.test/welcome', 'client_id' => 'cid_1'])
        ->and($sent[23]['body'])->toBe(['identifier' => 'https://api.test', 'name' => 'API', 'scopes' => [['key' => 'reports:read', 'description' => 'Read reports', 'tenant_requestable' => false]], 'client_id' => 'cid_1'])
        ->and($sent[28]['body'])->toBe(['user_id' => 'user_1', 'organization_id' => 'org_1', 'client_id' => 'cid_1', 'reason' => 'Ticket #42']);

    expect($roles[0]->roleId)->toBe('role_1')
        ->and($all[0]->tenantAssignable)->toBeFalse()
        ->and($blueprint->document)->toBe(['name' => 'Portal', 'scopes' => ['openid']])
        ->and($session->actorSubject)->toBe('staff_1');
});

it('reads a page and its cursor', function (): void {
    Http::fake(['*' => Http::response([
        'data' => [['user_id' => 'user_1', 'role' => 'owner'], ['user_id' => 'user_2', 'role' => 'something-new']],
        'meta' => ['limit' => 2, 'has_more' => true, 'next_cursor' => 'user_2'],
    ])]);

    $page = management()->members('org_1', limit: 2);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[0]->role)->toBe(OrganizationRole::Owner)
        ->and($page->items[1]->role)->toBeNull()
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextCursor)->toBe('user_2');
});

it('answers a missing environment role with false rather than an exception', function (): void {
    Http::fake(['*' => Http::response(['error' => 'not_found', 'message' => 'Role assignment not found.'], 404)]);

    expect(management()->hasEnvironmentRole('user_1', 'role_1'))->toBeFalse();
});

it('turns refusals into typed errors that keep the stable code', function (): void {
    Http::fake([
        '*/organizations/missing' => Http::response(['error' => 'not_found', 'message' => 'Organization not found.'], 404),
        '*/organizations' => Http::response(['error' => 'validation_failed', 'message' => 'The slug is taken.', 'errors' => ['slug' => ['That slug is already in use.']]], 422),
        '*/members' => Http::response(['error' => 'insufficient_scope', 'message' => 'This key lacks members:write.'], 403),
        '*/roles' => Http::response(['error' => 'rate_limited', 'message' => 'Slow down.'], 429, ['Retry-After' => '7']),
    ]);

    expect(fn () => management()->organization('missing'))->toThrow(ResourceNotFound::class, 'Organization not found.');

    try {
        management()->createOrganization(new NewOrganization('Acme', 'acme'));
        $this->fail('Expected a validation failure.');
    } catch (ValidationFailed $e) {
        expect($e->error)->toBe('validation_failed')->and($e->errors)->toBe(['slug' => ['That slug is already in use.']]);
    }

    try {
        management()->addMember('org_1', 'user_1');
        $this->fail('Expected a refusal.');
    } catch (ManagementApiException $e) {
        expect($e->isForbidden())->toBeTrue()->and($e->error)->toBe('insufficient_scope');
    }

    try {
        management()->roles();
        $this->fail('Expected a refusal.');
    } catch (ManagementApiException $e) {
        expect($e->isRateLimited())->toBeTrue()->and($e->retryAfter)->toBe(7);
    }
});

it('encodes ids so data cannot add a path', function (): void {
    Http::fake(['*' => Http::response(['data' => ['id' => 'x', 'name' => 'x']])]);

    management()->organization('../users/admin');

    expect(sentRequests()[0]['path'])->toBe('/api/v1/organizations/..%2Fusers%2Fadmin');
});

it('names the missing management key before sending anything', function (): void {
    config(['cbox-id-client.management.key' => null]);
    $this->app->forgetInstance(Management::class);
    Http::fake();

    expect(fn () => management()->organizations())->toThrow(NotConfigured::class, 'CBOX_ID_MANAGEMENT_KEY');
    Http::assertNothingSent();
});

it('uses an explicit management URL when one is configured', function (): void {
    config(['cbox-id-client.management.url' => 'https://api.acme.test/v1/']);
    $this->app->forgetInstance(Management::class);
    Http::fake(['*' => Http::response(['data' => []])]);

    management()->organizations();

    expect(Http::recorded()[0][0]->url())->toStartWith('https://api.acme.test/v1/organizations');
});
