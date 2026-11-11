<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\AssignableMemberRole;
use Cbox\Id\Client\Exceptions\CboxIdApiException;
use Cbox\Id\Client\Exceptions\ValidationFailed;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\ManagementClients;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\Page as TransportPage;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\Plane;
use Cbox\Id\Client\Management\Transport\V1Router;
use Cbox\Id\Client\Management\Transport\V1RouteTable;
use Cbox\Id\Client\Management\Transport\V1Shape;
use Cbox\Id\Client\Support\PublicId;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * The management clients on the /v1 API (CBOX_ID_API_URL set) hand the app exactly what
 * they handed it on {issuer}/api/v1: the same values, and bare ids where /v1 says usr_01j….
 *
 * Each scenario runs twice against a fake Cbox ID: once on /api/v1, once on /v1. The /v1
 * fake builds its answer from the same /api/v1 answer the way Cbox ID does (its
 * V1Responses middleware and PublicIdTree, ported below), so what the two runs hand back
 * must be equal.
 */

const V1C_ORG = '01j9z3k4m5n6p7q8r9s0t1v2w3';
const V1C_PARENT = '01j9z3k4m5n6p7q8r9s0t1v2w4';
const V1C_USR = '01jabcdefghjkmnpqrstvwxyz0';
const V1C_USR2 = '01jabcdefghjkmnpqrstvwxyz1';
const V1C_ROLE = '01jb0000000000000000000001';
const V1C_IVT = '01jc0000000000000000000001';
const V1C_APR = '01jd0000000000000000000001';
const V1C_ENV = '01je0000000000000000000001';

const V1C_ISSUER = 'https://acme.cboxid.test';
const V1C_API = 'https://api.cboxid.test';

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => V1C_ISSUER,
        'cbox-id-client.management.key' => 'cbid_env_test',
        'cbox-id-client.management.organization_key' => 'cbid_org_test',
        'cbox-id-client.management.root_url' => V1C_API,
    ]);
});

// -- What Cbox ID does to an /api/v1 answer on /v1 (V1Responses + PublicIdTree) -----------

/** @return array<string, string> */
function v1cPrefixes(): array
{
    return [
        'organization' => 'org', 'user' => 'usr', 'membership' => 'mbr', 'invitation' => 'ivt', 'project' => 'prj',
        'environment' => 'env', 'role' => 'rol', 'permission' => 'perm', 'app' => 'app', 'approval' => 'apr',
        'webhook_endpoint' => 'whe', 'session' => 'ses',
    ];
}

function v1cKind(string $name, string $table): ?string
{
    $fields = [
        'organization' => 'organization', 'workspace' => 'organization', 'user' => 'user', 'operator' => 'user',
        'membership' => 'membership', 'invitation' => 'invitation', 'project' => 'project', 'environment' => 'environment',
        'role' => 'role', 'permission' => 'permission', 'app' => 'app', 'approval' => 'approval', 'session' => 'session',
    ];
    $nested = ['organization' => 'organization', 'user' => 'user', 'environment' => 'environment', 'project' => 'project', 'role' => 'role', 'membership' => 'membership', 'invitation' => 'invitation', 'app' => 'app', 'session' => 'session'];
    $objects = [
        'organization' => 'organization', 'user' => 'user', 'member' => 'membership', 'organization_membership' => 'membership',
        'invitation' => 'invitation', 'organization_invitation' => 'invitation', 'role' => 'role', 'role_assignment' => null,
        'approval' => 'approval', 'environment' => 'environment', 'app' => 'app',
    ];

    return match ($table) {
        'field' => preg_match('/\A([a-z_]+?)_ids?\z/', $name, $m) === 1 ? ($fields[$m[1]] ?? null) : null,
        'nested' => $nested[rtrim($name, 's')] ?? null,
        default => $objects[$name] ?? null,
    };
}

function v1cEncodeId(?string $kind, mixed $value): mixed
{
    if ($kind === null || ! is_string($value) || preg_match('/\A[0-7][0-9a-hjkmnp-tv-z]{25}\z/i', $value) !== 1) {
        return $value;
    }

    return v1cPrefixes()[$kind].'_'.$value;
}

/**
 * @param  array<array-key, mixed>  $node
 * @return array<array-key, mixed>
 */
function v1cEncode(array $node, ?string $kind): array
{
    if (isset($node['object']) && is_string($node['object'])) {
        $kind = v1cKind($node['object'], 'object');
    }

    foreach ($node as $key => $value) {
        if (! is_string($key)) {
            $node[$key] = is_array($value) ? v1cEncode($value, $kind) : $value;

            continue;
        }

        if (in_array($key, ['metadata', 'custom_attributes', 'properties', 'claims', 'actor', 'targets', 'context', 'settings', 'raw'], true)) {
            continue;
        }

        if ($key === 'id') {
            $node[$key] = v1cEncodeId($kind, $value);

            continue;
        }

        $field = $key === 'parent_id' && $kind === 'organization' ? 'organization' : v1cKind($key, 'field');

        if ($field !== null) {
            $node[$key] = is_array($value) ? array_map(static fn (mixed $one): mixed => v1cEncodeId($field, $one), $value) : v1cEncodeId($field, $value);

            continue;
        }

        if (is_array($value)) {
            $node[$key] = v1cEncode($value, v1cKind($key, 'nested'));
        }
    }

    return $node;
}

/**
 * The /v1 body Cbox ID answers for an /api/v1 body.
 *
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function v1cShape(array $body, int $status, string $object): array
{
    if ($status >= 400) {
        $extra = array_diff_key($body, array_flip(['error', 'message', 'error_description']));

        return [
            'type' => 'https://docs.cboxid.com/errors/'.($body['error'] ?? 'error'),
            'title' => 'Error',
            'status' => $status,
            'detail' => $body['message'] ?? 'The request failed.',
            'error' => $body['error'] ?? 'error',
            ...$extra,
        ];
    }

    if (($body['error'] ?? null) === 'approval_required') {
        return v1cEncode(['object' => 'approval', ...$body['approval'], 'detail' => $body['message'] ?? null], 'approval');
    }

    $kind = v1cKind($object, 'object');

    if (! array_key_exists('data', $body)) {
        return v1cEncode($body, $kind);
    }

    $data = $body['data'];

    if (is_array($data) && array_is_list($data)) {
        return [
            'object' => 'list',
            'data' => array_map(static fn (mixed $item): mixed => is_array($item) ? v1cEncode($item, $kind) : $item, $data),
            'has_more' => (bool) ($body['meta']['has_more'] ?? false),
            'next_cursor' => ($body['meta']['has_more'] ?? false) === true ? ($body['meta']['next_cursor'] ?? null) : null,
        ];
    }

    return v1cEncode(['object' => $data['object'] ?? $object, ...$data], $kind);
}

// -- The fake Cbox ID ---------------------------------------------------------------------

/**
 * One exchange the fake answers: the /api/v1 request and the /v1 one that replaces it, and
 * the /api/v1 answer (the /v1 one is derived from it).
 *
 * @param  array<string, mixed>|null  $body
 * @return array<string, mixed>
 */
function v1cRoute(string $legacy, string $v1, ?array $body, int $status = 200, string $object = 'object'): array
{
    return ['legacy' => $legacy, 'v1' => $v1, 'body' => $body, 'status' => $status, 'object' => $object];
}

/**
 * @param  list<array<string, mixed>>  $routes
 * @return ArrayObject<int, string>
 */
function v1cFake(array $routes, bool $v1): ArrayObject
{
    $seen = new ArrayObject;
    Sleep::fake();
    Http::swap(new Factory);
    Http::fake(function (HttpRequest $request) use ($routes, $v1, $seen) {
        $line = $request->method().' '.$request->url();
        $seen->append($line);

        foreach ($routes as $route) {
            if ($route[$v1 ? 'v1' : 'legacy'] !== $line) {
                continue;
            }

            if ($route['body'] === null) {
                return Http::response('', $route['status']);
            }

            // What stays on /api/v1 is answered in the /api/v1 shape, in either run.
            $onV1 = str_starts_with($request->url(), V1C_API.'/v1/');
            $body = $onV1 ? v1cShape($route['body'], $route['status'], $route['object']) : $route['body'];

            return Http::response($body, $route['status'], $onV1 && $route['status'] >= 400 ? ['Content-Type' => 'application/problem+json'] : []);
        }

        throw new RuntimeException("The fake Cbox ID has no answer for {$line}");
    });

    return $seen;
}

/**
 * Run `$call` on /api/v1 and on /v1 against the same answers.
 *
 * @param  list<array<string, mixed>>  $routes
 * @return array{legacy: mixed, v1: mixed, legacySeen: list<string>, v1Seen: list<string>}
 */
function v1cBothWays(array $routes, Closure $call): array
{
    $run = function (bool $v1) use ($routes, $call): array {
        config(['cbox-id-client.api_url' => $v1 ? V1C_API : null]);
        app()->forgetInstance(Management::class);
        app()->forgetInstance(ManagementClients::class);
        $seen = v1cFake($routes, $v1);

        try {
            $result = $call();
        } catch (Throwable $e) {
            $result = $e;
        }

        return [$result, $seen->getArrayCopy()];
    };

    [$legacy, $legacySeen] = $run(false);
    [$v1, $v1Seen] = $run(true);

    return ['legacy' => $legacy, 'v1' => $v1, 'legacySeen' => $legacySeen, 'v1Seen' => $v1Seen];
}

/** What app code sees of a result: its public values, minus per-request noise. */
function v1cAppView(mixed $value): mixed
{
    return match (true) {
        $value instanceof ApiResponse => ['data' => v1cAppView($value->data), 'status' => $value->status],
        $value instanceof TransportPage => ['items' => v1cAppView($value->items), 'has_more' => $value->hasMore, 'next_cursor' => $value->nextCursor, 'total' => $value->total],
        $value instanceof CboxIdApiException => [$value::class, $value->status, $value->error, $value->getMessage(), $value->errors],
        $value instanceof Throwable => [$value::class, $value->getMessage(), get_object_vars($value)],
        $value instanceof Generator => v1cAppView(iterator_to_array($value, false)),
        $value instanceof UnitEnum => $value,
        $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
        is_object($value) => [$value::class, v1cAppView(get_object_vars($value))],
        is_array($value) => array_map(v1cAppView(...), $value),
        default => $value,
    };
}

/** Every string anywhere in an app-facing value. @return list<string> */
function v1cStrings(mixed $value): array
{
    $view = v1cAppView($value);
    $out = [];
    array_walk_recursive($view, static function (mixed $leaf) use (&$out): void {
        if (is_string($leaf)) {
            $out[] = $leaf;
        }
    });

    return $out;
}

/** @param array{legacy: mixed, v1: mixed} $runs */
function v1cExpectSame(array $runs): void
{
    expect($runs['v1'])->not->toBeInstanceOf(RuntimeException::class, 'the fake had no /v1 answer: '.($runs['v1'] instanceof Throwable ? $runs['v1']->getMessage() : ''));
    expect(v1cAppView($runs['v1']))->toEqual(v1cAppView($runs['legacy']));

    // A public id the app sees on /v1 is one it saw on /api/v1 too: a customer's own data.
    $legacy = v1cStrings($runs['legacy']);

    foreach (v1cStrings($runs['v1']) as $string) {
        if (V1Shape::isPublicId($string)) {
            expect(in_array($string, $legacy, true))->toBeTrue("a public id reached app code: {$string}");
        }
    }
}

// -- Fixtures (as /api/v1 answers them) ----------------------------------------------------

function v1cOrganization(array $overrides = []): array
{
    return [
        'id' => V1C_ORG, 'name' => 'Acme', 'slug' => 'acme', 'type' => 'customer', 'status' => 'active',
        'parent_id' => V1C_PARENT, 'created_at' => '2026-11-01T10:00:00+00:00',
        'metadata' => ['crm_owner' => 'usr_'.V1C_USR2],
        ...$overrides,
    ];
}

function v1cMember(string $userId = V1C_USR): array
{
    return [
        'user_id' => $userId, 'organization_id' => V1C_ORG, 'role' => 'admin', 'email' => 'ada@acme.test', 'name' => 'Ada',
        'status' => 'active', 'joined_at' => '2026-11-02T10:00:00+00:00',
        'user' => ['id' => $userId, 'email' => 'ada@acme.test'],
        'environment_ids' => [V1C_ENV],
    ];
}

function v1cAssignment(): array
{
    return ['role_id' => V1C_ROLE, 'key' => 'editor', 'name' => 'Editor', 'organization_id' => V1C_ORG, 'user_id' => V1C_USR, 'client_id' => 'cid_billing', 'tenant_assignable' => true, 'source' => 'direct'];
}

function v1cInvitation(): array
{
    return ['id' => V1C_IVT, 'email' => 'grace@acme.test', 'role' => 'member', 'roles' => [], 'organization_id' => V1C_ORG, 'status' => 'pending', 'client_id' => 'cid_billing', 'expires_at' => '2026-11-20T10:00:00+00:00', 'accepted_by' => null];
}

// -- Contracts\Management (CboxIdManagement), what the apps' directories call --------------

it('reads organizations alike, the page, one, and a new one', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $runs = v1cBothWays([
        v1cRoute("GET {$e}/organizations?limit=50", "GET {$v}/organizations?limit=50", ['data' => [v1cOrganization(), v1cOrganization(['id' => V1C_PARENT, 'parent_id' => null])], 'meta' => ['has_more' => true, 'next_cursor' => V1C_PARENT, 'limit' => 50]], 200, 'organization'),
        v1cRoute("GET {$e}/organizations/".V1C_ORG, "GET {$v}/organizations/".V1C_ORG, ['data' => v1cOrganization()], 200, 'organization'),
        v1cRoute("POST {$e}/organizations", "POST {$v}/organizations", ['data' => v1cOrganization()], 201, 'organization'),
    ], fn (): array => [
        app(Management::class)->organizations(),
        app(Management::class)->organization(V1C_ORG),
        app(Management::class)->createOrganization(new NewOrganization('Acme', ownerUserId: V1C_USR)),
    ]);

    v1cExpectSame($runs);
    expect($runs['v1'][1]->id)->toBe(V1C_ORG)
        ->and($runs['v1'][1]->parentId)->toBe(V1C_PARENT)
        // A customer's own metadata is theirs: it keeps the id it was given.
        ->and($runs['v1'][1]->attributes['metadata']['crm_owner'] ?? null)->toBe('usr_'.V1C_USR2);
});

it('reads members, adds one, and reads and replaces their roles alike, with the children addressed directly on /v1', function (): void {
    // The member list stays on /api/v1: /v1 would drop meta.directory_revision.
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $org = V1C_ORG;
    $usr = V1C_USR;
    $runs = v1cBothWays([
        v1cRoute("GET {$e}/organizations/{$org}/members?limit=50", "GET {$e}/organizations/{$org}/members?limit=50", ['data' => [v1cMember(), v1cMember(V1C_USR2)], 'meta' => ['has_more' => false, 'next_cursor' => null, 'directory_revision' => 7]], 200, 'organization_membership'),
        v1cRoute("POST {$e}/organizations/{$org}/members", "POST {$v}/organization-memberships?organization_id={$org}", ['data' => v1cMember()], 201, 'organization_membership'),
        v1cRoute("GET {$e}/organizations/{$org}/members/{$usr}/roles", "GET {$v}/role-assignments?organization_id={$org}&user_id={$usr}", ['data' => [v1cAssignment()]], 200, 'role_assignment'),
        v1cRoute("PUT {$e}/organizations/{$org}/members/{$usr}/apps/cid_billing/roles", "PUT {$v}/organization-memberships/{$usr}/app-roles?organization_id={$org}&client_id=cid_billing", ['data' => [v1cAssignment()]], 200, 'role_assignment'),
        v1cRoute("PUT {$e}/organizations/{$org}/members/{$usr}/roles/editor?client_id=cid_billing", "PUT {$v}/role-assignments/editor?organization_id={$org}&user_id={$usr}&client_id=cid_billing", ['data' => v1cAssignment()], 200, 'role_assignment'),
    ], fn (): array => [
        app(Management::class)->members(V1C_ORG),
        app(Management::class)->addMember(V1C_ORG, V1C_USR, AssignableMemberRole::Admin),
        app(Management::class)->memberRoles(V1C_ORG, V1C_USR),
        app(Management::class)->replaceAppRoles(V1C_ORG, V1C_USR, 'cid_billing', ['editor'], 'v1'),
        app(Management::class)->assignRole(V1C_ORG, V1C_USR, 'editor', 'cid_billing'),
    ]);

    v1cExpectSame($runs);
    expect($runs['v1'][0]->directoryRevision)->toBe(7)
        ->and($runs['v1'][1]->userId)->toBe(V1C_USR)
        ->and($runs['v1'][1]->attributes['user']['id'] ?? null)->toBe(V1C_USR)
        ->and($runs['v1'][1]->attributes['environment_ids'] ?? null)->toBe([V1C_ENV])
        ->and($runs['v1'][2][0]->roleId)->toBe(V1C_ROLE)
        ->and($runs['v1'][2][0]->clientId)->toBe('cid_billing');
});

it('reads, sends, resends and revokes invitations alike', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $org = V1C_ORG;
    $ivt = V1C_IVT;
    $runs = v1cBothWays([
        v1cRoute("GET {$e}/organizations/{$org}/invitations?limit=50", "GET {$v}/organization-invitations?organization_id={$org}&limit=50", ['data' => [v1cInvitation()], 'meta' => ['has_more' => false, 'next_cursor' => null]], 200, 'organization_invitation'),
        v1cRoute("POST {$e}/organizations/{$org}/invitations", "POST {$v}/organization-invitations?organization_id={$org}", ['data' => v1cInvitation()], 201, 'organization_invitation'),
        v1cRoute("POST {$e}/organizations/{$org}/invitations/{$ivt}/resend", "POST {$v}/organization-invitations/{$ivt}/resend?organization_id={$org}", ['data' => v1cInvitation()], 200, 'organization_invitation'),
        v1cRoute("DELETE {$e}/organizations/{$org}/invitations/{$ivt}", "DELETE {$v}/organization-invitations/{$ivt}?organization_id={$org}", null, 204),
    ], fn (): array => [
        app(Management::class)->invitations(V1C_ORG),
        app(Management::class)->invite(V1C_ORG, new NewInvitation('grace@acme.test')),
        app(Management::class)->resendInvitation(V1C_ORG, V1C_IVT),
        app(Management::class)->revokeInvitation(V1C_ORG, V1C_IVT),
    ]);

    v1cExpectSame($runs);
    expect($runs['v1'][0]->items[0]->id)->toBe(V1C_IVT)
        ->and($runs['v1'][0]->items[0]->organizationId)->toBe(V1C_ORG);
});

it('reads the same failures: a problem becomes the same exception', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $runs = v1cBothWays([
        v1cRoute("POST {$e}/organizations", "POST {$v}/organizations", ['error' => 'validation_failed', 'message' => 'The name is taken.', 'errors' => ['name' => ['The name is taken.']]], 422),
    ], fn (): mixed => app(Management::class)->createOrganization(new NewOrganization('Acme')));

    expect($runs['v1'])->toBeInstanceOf(ValidationFailed::class)
        ->and(v1cAppView($runs['v1']))->toEqual(v1cAppView($runs['legacy']));
});

it('answers hasEnvironmentRole from a /v1 404 as from an /api/v1 one', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $usr = V1C_USR;
    $runs = v1cBothWays([
        v1cRoute("GET {$e}/users/{$usr}/environment-roles/staff", "GET {$v}/environment-role-assignments/staff?user_id={$usr}", ['error' => 'not_found', 'message' => 'No such role assignment.'], 404),
    ], fn (): bool => app(Management::class)->hasEnvironmentRole(V1C_USR, 'staff'));

    expect($runs['legacy'])->toBeFalse()->and($runs['v1'])->toBeFalse();
});

// -- The typed clients (CboxIdApi) ----------------------------------------------------------

it('reads the typed environment plane alike: a resource, a page, and the escape hatch', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $org = V1C_ORG;
    $usr = V1C_USR;
    $runs = v1cBothWays([
        v1cRoute("GET {$e}/organizations/{$org}", "GET {$v}/organizations/{$org}", ['data' => v1cOrganization()], 200, 'organization'),
        v1cRoute("GET {$e}/organizations?limit=2", "GET {$v}/organizations?limit=2", ['data' => [v1cOrganization()], 'meta' => ['has_more' => true, 'next_cursor' => V1C_ORG, 'limit' => 2]], 200, 'organization'),
        v1cRoute("GET {$e}/users/{$usr}", "GET {$v}/users/{$usr}", ['data' => ['id' => $usr, 'email' => 'ada@acme.test', 'organization_ids' => [$org], 'organizations' => [['id' => $org, 'role' => 'admin']]]], 200, 'user'),
        v1cRoute("GET {$e}/organizations/{$org}/members/{$usr}/roles", "GET {$v}/role-assignments?organization_id={$org}&user_id={$usr}", ['data' => [v1cAssignment()]], 200, 'role_assignment'),
    ], function (): array {
        $env = app(ManagementClients::class)->environment();

        return [
            $env->organizations->get(V1C_ORG),
            $env->organizations->list(['limit' => 2]),
            $env->users->get(V1C_USR),
            $env->request('GET', '/organizations/'.V1C_ORG.'/members/'.V1C_USR.'/roles'),
        ];
    });

    v1cExpectSame($runs);
    expect($runs['v1Seen'])->toBe([
        "GET {$v}/organizations/{$org}",
        "GET {$v}/organizations?limit=2",
        "GET {$v}/users/{$usr}",
        "GET {$v}/role-assignments?organization_id={$org}&user_id={$usr}",
    ]);
});

it('moves the organization plane to /v1/organization, and keeps a page-numbered list on /api/v1', function (): void {
    $root = V1C_API.'/api/v1';
    $v = V1C_API.'/v1';
    $runs = v1cBothWays([
        v1cRoute("GET {$root}/workspace", "GET {$v}/organization", ['data' => ['id' => V1C_ORG, 'name' => 'Acme', 'slug' => 'acme']], 200, 'organization'),
        // /v1 would drop meta.total and meta.next_page: this one stays on /api/v1 either way.
        v1cRoute("GET {$root}/workspace/members", "GET {$root}/workspace/members", ['data' => [['id' => V1C_USR, 'email' => 'ada@acme.test']], 'meta' => ['has_more' => true, 'page' => 1, 'next_page' => 2, 'total' => 3]]),
    ], function (): array {
        $organization = app(ManagementClients::class)->organization();

        return [$organization->workspace->get(), $organization->team->list()];
    });

    v1cExpectSame($runs);
    expect($runs['v1Seen'])->toBe(["GET {$v}/organization", "GET {$root}/workspace/members"])
        ->and($runs['v1'][1]->total)->toBe(3);
});

it('waits out an approval on /v1 alike, and hands the app the bare approval id', function (): void {
    $e = V1C_ISSUER.'/api/v1';
    $v = V1C_API.'/v1';
    $org = V1C_ORG;
    $apr = V1C_APR;
    $approval = static fn (string $poll): array => [
        'error' => 'approval_required',
        'message' => 'This action needs approval.',
        'approval' => ['id' => $apr, 'status' => 'pending', 'binding_code' => '42-17', 'expires_at' => now()->addMinutes(5)->toIso8601String(), 'poll_url' => $poll],
    ];
    $seenApprovals = [];
    $answers = static function (bool $v1) use ($e, $v, $org, $apr, $approval): Closure {
        $held = false;

        return function (HttpRequest $request) use (&$held, $v1, $e, $v, $org, $apr, $approval) {
            $line = $request->method().' '.$request->url();
            $poll = $v1 ? "{$v}/approvals/apr_{$apr}" : "{$e}/action-approvals/{$apr}";

            if ($line === 'DELETE '.($v1 ? $v : $e)."/organizations/{$org}") {
                if (! $held) {
                    $held = true;
                    $body = $approval($poll);

                    return Http::response($v1 ? v1cShape($body, 202, 'organization') : $body, 202);
                }

                // The repeat carries the approval, in the form the app was handed.
                expect($request->header('Cbox-Approval'))->toBe([$apr]);
                $body = ['data' => v1cOrganization(['status' => 'archived'])];

                return Http::response($v1 ? v1cShape($body, 200, 'organization') : $body);
            }

            if ($line === "GET {$poll}") {
                $body = ['data' => ['id' => $apr, 'status' => 'approved']];

                return Http::response($v1 ? v1cShape($body, 200, 'approval') : $body);
            }

            throw new RuntimeException("No answer for {$line}");
        };
    };

    $run = function (bool $v1) use ($answers, &$seenApprovals): mixed {
        config(['cbox-id-client.api_url' => $v1 ? V1C_API : null]);
        Sleep::fake();
        Http::swap(new Factory);
        Http::fake($answers($v1));
        $client = new EnvironmentClient(
            baseUrl: V1C_ISSUER,
            apiKey: 'cbid_env_test',
            onApprovalRequired: function (PendingApproval $approval, ApprovalContext $context) use (&$seenApprovals): void {
                $seenApprovals[] = [$approval->id, $approval->status, $approval->bindingCode, $context->action, $context->method, $context->path];
            },
        );

        return $client->organizations->delete(V1C_ORG);
    };

    $legacy = $run(false);
    $v1 = $run(true);

    v1cExpectSame(['legacy' => $legacy, 'v1' => $v1]);
    expect($seenApprovals[1])->toBe($seenApprovals[0])
        ->and($seenApprovals[1][0])->toBe(V1C_APR);
});

// -- Ids in, ids out ---------------------------------------------------------------------------

it('takes an id in either form, and passes it on as given', function (string $given): void {
    config(['cbox-id-client.api_url' => V1C_API]);
    app()->forgetInstance(Management::class);
    $v = V1C_API.'/v1';
    $seen = v1cFake([
        v1cRoute('-', "GET {$v}/organizations/{$given}", ['data' => v1cOrganization()], 200, 'organization'),
        v1cRoute('-', "GET {$v}/role-assignments?organization_id={$given}&user_id=".V1C_USR, ['data' => [v1cAssignment()]], 200, 'role_assignment'),
    ], true);

    $organization = app(Management::class)->organization($given);
    $roles = app(Management::class)->memberRoles($given, V1C_USR);

    expect($seen->getArrayCopy())->toHaveCount(2)
        ->and($organization->id)->toBe(V1C_ORG)
        ->and($roles[0]->organizationId)->toBe(V1C_ORG);
})->with(['bare' => V1C_ORG, 'prefixed' => 'org_'.V1C_ORG]);

it('never hands app code a public id, at any depth, and leaves everything else alone', function (): void {
    $v1 = [
        'object' => 'organization_membership',
        'id' => 'mbr_'.V1C_USR,
        'user_id' => 'usr_'.V1C_USR,
        'organization_id' => 'org_'.V1C_ORG,
        'environment_ids' => ['env_'.V1C_ENV, 'staging'],
        'client_id' => 'cid_billing',
        'external_id' => 'usr_'.V1C_USR2,
        'request_id' => 'req_'.V1C_USR2,
        'user' => ['id' => 'usr_'.V1C_USR, 'sessions' => [['id' => 'ses_'.V1C_USR2, 'device_id' => 'dvc_'.V1C_USR2]]],
        'metadata' => ['owner_id' => 'usr_'.V1C_USR2],
        'actor' => ['id' => 'usr_'.V1C_USR2],
        'slug' => 'org_'.V1C_ORG,
    ];

    expect(V1Shape::legacyBody($v1, 200, 'data'))->toBe(['data' => [
        'id' => V1C_USR,
        'user_id' => V1C_USR,
        'organization_id' => V1C_ORG,
        'environment_ids' => [V1C_ENV, 'staging'],
        'client_id' => 'cid_billing',
        'external_id' => 'usr_'.V1C_USR2,
        'request_id' => 'req_'.V1C_USR2,
        'user' => ['id' => V1C_USR, 'sessions' => [['id' => V1C_USR2, 'device_id' => V1C_USR2]]],
        'metadata' => ['owner_id' => 'usr_'.V1C_USR2],
        'actor' => ['id' => 'usr_'.V1C_USR2],
        'slug' => 'org_'.V1C_ORG,
    ]]);

    // Every prefix Cbox ID registers unwraps through PublicId::raw.
    foreach (V1Shape::PREFIXES as $prefix) {
        expect(V1Shape::rawId($prefix.'_'.V1C_ORG))->toBe(PublicId::raw($prefix.'_'.V1C_ORG))->toBe(V1C_ORG);
    }

    expect(V1Shape::rawId('doc_'.V1C_ORG))->toBe('doc_'.V1C_ORG);
});

it('reads a /v1 list, problem and approval back into the /api/v1 shapes', function (): void {
    expect(V1Shape::legacyBody(['object' => 'list', 'data' => [['object' => 'role', 'id' => 'rol_'.V1C_ROLE]], 'has_more' => true, 'next_cursor' => 'c2'], 200, 'list'))
        ->toBe(['data' => [['object' => 'role', 'id' => V1C_ROLE]], 'meta' => ['has_more' => true, 'next_cursor' => 'c2']])
        ->and(V1Shape::legacyBody(['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null], 200, 'data'))
        ->toBe(['data' => []])
        ->and(V1Shape::legacyBody(['type' => 'https://docs.cboxid.com/errors/forbidden', 'title' => 'Forbidden', 'status' => 403, 'detail' => 'Missing scope.', 'error' => 'insufficient_scope', 'request_id' => 'req_1'], 403, 'data'))
        ->toBe(['error' => 'insufficient_scope', 'message' => 'Missing scope.', 'request_id' => 'req_1'])
        ->and(V1Shape::legacyBody(['object' => 'approval', 'id' => 'apr_'.V1C_APR, 'status' => 'pending', 'binding_code' => '1', 'expires_at' => 'x', 'poll_url' => 'https://api.cboxid.test/v1/approvals/apr_'.V1C_APR, 'detail' => 'Needs approval.'], 202, 'data'))
        ->toBe(['error' => 'approval_required', 'message' => 'Needs approval.', 'approval' => ['id' => V1C_APR, 'status' => 'pending', 'binding_code' => '1', 'expires_at' => 'x', 'poll_url' => 'https://api.cboxid.test/v1/approvals/apr_'.V1C_APR]]);
});

// -- Without CBOX_ID_API_URL nothing changes -------------------------------------------------

it('sends every management call to {issuer}/api/v1, untouched, without CBOX_ID_API_URL', function (): void {
    config(['cbox-id-client.api_url' => null]);
    $raw = ['data' => ['user_id' => 'usr_'.V1C_USR, 'organization_id' => V1C_ORG, 'role' => 'admin', 'object' => 'organization_membership']];
    $seen = v1cFake([v1cRoute('POST '.V1C_ISSUER.'/api/v1/organizations/'.V1C_ORG.'/members', '-', $raw, 201)], false);

    $member = app(Management::class)->addMember(V1C_ORG, V1C_USR);
    $typed = envClient(baseUrl: V1C_ISSUER);

    expect($seen->getArrayCopy())->toBe(['POST '.V1C_ISSUER.'/api/v1/organizations/'.V1C_ORG.'/members'])
        // What /api/v1 says is what the app gets: nothing is rewritten on this path.
        ->and($member->userId)->toBe('usr_'.V1C_USR)
        ->and(V1Router::configured())->toBeNull()
        ->and($typed->transport->baseUrl)->toBe(V1C_ISSUER.'/api/v1');
});

// -- The route table ---------------------------------------------------------------------------

it('moves every operation to a /v1 route the vendored /v1 document serves, each parameter placed once', function (): void {
    $spec = json_decode((string) file_get_contents(__DIR__.'/../openapi/v1.json'), true, 512, JSON_THROW_ON_ERROR);
    $served = [];

    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $served[strtoupper($method).' '.preg_replace('/\{[^}]+\}/', '{}', substr($path, 3))] = true;
        }
    }

    $count = 0;

    foreach (V1RouteTable::ROUTES as $plane => $routes) {
        $operations = 'Cbox\\Id\\Client\\Management\\'.match (Plane::from($plane)) {
            Plane::Environment => 'Environment', Plane::Workspace => 'Workspace', Plane::Account => 'Account', Plane::Platform => 'Platform',
        }.'\\Operations';
        $legacy = [];

        foreach ($operations::all() as $op) {
            $legacy[$op->method.' '.$op->path] = $op;
        }

        foreach ($routes as $key => [$method, $path, $params, $query, $shape]) {
            $count++;
            expect($legacy)->toHaveKey($key)
                ->and($served)->toHaveKey($method.' '.$path)
                ->and(in_array($shape, ['list', 'data', 'bare'], true))->toBeTrue()
                ->and(substr_count($path, '{}'))->toBe(count($params));

            $placed = [...$params, ...array_keys($query)];
            sort($placed);
            $expected = $legacy[$key]->pathParams;
            sort($expected);
            expect($placed)->toBe($expected, $key);
        }

        foreach (array_keys(V1RouteTable::STAYS[$plane] ?? []) as $key) {
            expect($legacy)->toHaveKey($key)->and($routes)->not->toHaveKey($key);
        }
    }

    expect($count)->toBeGreaterThan(300);
});

it('keeps the /v1 route table in step with the vendored documents', function (): void {
    exec(PHP_BINARY.' '.escapeshellarg(__DIR__.'/../bin/generate-v1-routes').' --check', $output, $exit);

    expect($exit)->toBe(0, implode("\n", $output));
});
