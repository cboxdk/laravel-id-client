<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Exceptions\ValidationFailed;
use Cbox\Id\Client\Facades\CboxIdManagement;
use Cbox\Id\Client\Management\Data\NewOrganization;
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

it('reads a page and its cursor', function (): void {
    Http::fake(['*' => Http::response([
        'data' => [['id' => 'm_1', 'user_id' => 'user_1', 'role' => 'owner'], ['id' => 'm_2', 'user_id' => 'user_2', 'role' => 'something-new']],
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
