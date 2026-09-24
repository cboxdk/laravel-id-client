<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\ApiKeyStatus;
use Cbox\Id\Client\Enums\AssignableMemberRole;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Management\Data\ApiChanges;
use Cbox\Id\Client\Management\Data\ApiScope;
use Cbox\Id\Client\Management\Data\AppBlueprint;
use Cbox\Id\Client\Management\Data\NewApi;
use Cbox\Id\Client\Management\Data\NewApp;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\Data\NewSupportSession;
use Cbox\Id\Client\Management\Data\OrganizationChanges;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

/**
 * The management client against the SERVER'S OWN CONTRACT.
 *
 * tests/Fixtures/openapi/environment.yaml is cbox-id's resources/openapi/environment.yaml,
 * copied verbatim. The fake server here answers every request with an example built from
 * that file's response schema, and every request the SDK sends is checked against it: the
 * operation exists, every body field is one the schema declares (and every required one
 * is sent), every query parameter is declared. When the server's contract moves, re-copy
 * the file and this fails where the SDK disagrees.
 */
function spec(): array
{
    static $spec = null;

    return $spec ??= Yaml::parseFile(__DIR__.'/Fixtures/openapi/environment.yaml');
}

function resolveRef(array $schema): array
{
    while (isset($schema['$ref'])) {
        $node = spec();

        foreach (explode('/', substr($schema['$ref'], 2)) as $part) {
            $node = $node[$part];
        }

        $schema = $node;
    }

    return $schema;
}

/** An example value for a schema: every declared property, a plausible value each. */
function example(array $schema, string $name = 'x'): mixed
{
    $schema = resolveRef($schema);

    if (array_key_exists('const', $schema)) {
        return $schema['const'];
    }

    if (isset($schema['enum'])) {
        return $schema['enum'][0];
    }

    $type = $schema['type'] ?? 'object';
    $type = is_array($type) ? array_values(array_filter($type, fn ($t) => $t !== 'null'))[0] : $type;

    if ($type === 'object') {
        $out = [];

        foreach ($schema['properties'] ?? [] as $prop => $sub) {
            $out[$prop] = example($sub, $prop);
        }

        return $out === [] ? new stdClass : $out;
    }

    return match ($type) {
        'array' => [example($schema['items'] ?? ['type' => 'string'], $name)],
        'boolean' => true,
        'integer' => 1,
        default => ($schema['format'] ?? null) === 'date-time' ? '2026-09-24T12:00:00+00:00' : $name.'_1',
    };
}

/** @return array{0: string, 1: array}|null the spec path template and its operation */
function operationFor(string $method, string $path): ?array
{
    $path = (string) preg_replace('#^/api/v1#', '', $path);

    foreach (spec()['paths'] as $template => $operations) {
        $pattern = '#^'.preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/]+', preg_quote($template, '#')).'$#';

        if (preg_match($pattern, $path) === 1 && isset($operations[strtolower($method)])) {
            return [$template, $operations[strtolower($method)]];
        }
    }

    return null;
}

beforeEach(function (): void {
    config(['cbox-id-client.issuer' => 'https://acme.cboxid.test', 'cbox-id-client.management.key' => 'cbid_env_test']);

    Http::fake(function (HttpRequest $request) {
        $op = operationFor($request->method(), (string) parse_url($request->url(), PHP_URL_PATH));

        if ($op === null) {
            return Http::response(['error' => 'not_in_spec', 'message' => 'Not in the spec.'], 400);
        }

        foreach (['200', '201', '204'] as $status) {
            if (isset($op[1]['responses'][$status])) {
                $schema = $op[1]['responses'][$status]['content']['application/json']['schema'] ?? null;

                return Http::response($schema !== null ? example($schema) : '', (int) $status);
            }
        }

        return Http::response('', 204);
    });
});

it('matches every path template of the spec it is given', function (): void {
    expect(operationFor('POST', '/api/v1/organizations/org_1/invitations/inv_1/resend')[0])->toBe('/organizations/{id}/invitations/{invitationId}/resend')
        ->and(operationFor('GET', '/api/v1/nowhere'))->toBeNull();
});

it('only calls operations the server declares, with fields and parameters it declares', function (): void {
    $m = app(Management::class);
    $blueprint = $m->appBlueprint('cid_1');

    $m->organizations(after: 'org_0', limit: 10);
    $m->organization('org_1');
    $m->createOrganization(new NewOrganization('Acme', 'acme', null, 'user_1', 'customer'));
    $m->updateOrganization('org_1', new OrganizationChanges(name: 'Acme Ltd'));
    $m->archiveOrganization('org_1');
    $m->members('org_1');
    $m->addMember('org_1', 'user_1', AssignableMemberRole::Admin);
    $m->updateMember('org_1', 'user_1', AssignableMemberRole::Member);
    $m->removeMember('org_1', 'user_1');
    $m->transferOwnership('org_1', 'user_2');
    $m->invitations('org_1');
    $m->invite('org_1', new NewInvitation('ada@example.test', AssignableMemberRole::Member, ['editor'], 'https://app.test/welcome', 'cid_1', 'Acme'));
    $m->revokeInvitation('org_1', 'inv_1');
    $m->resendInvitation('org_1', 'inv_1');
    $m->memberRoles('org_1', 'user_1');
    $m->assignRole('org_1', 'user_1', 'editor', 'cid_1');
    $m->unassignRole('org_1', 'user_1', 'role_1');
    $m->roles(clientId: 'cid_1', organizationId: 'org_1');
    $m->environmentRoles('user_1');
    $m->hasEnvironmentRole('user_1', 'support', 'cid_1');
    $m->grantEnvironmentRole('user_1', 'support', 'cid_1');
    $m->revokeEnvironmentRole('user_1', 'role_1');
    $m->apps();
    $m->createApp(new NewApp('Portal', 'advanced', ['https://app.test/cb'], [], 'confidential', ['authorization_code'], ['openid'], true, 'org_1', ['keys' => []]));
    $m->createApp(NewApp::fromBlueprint($blueprint, 'Portal (prod)', ['https://app.example/cb']));
    $m->apis();
    $m->api('api_1');
    $m->createApi(new NewApi('https://api.test', 'API', [new ApiScope('reports:read', 'Read reports', false)], 'cid_1', 'org_1'));
    $m->updateApi('api_1', new ApiChanges(name: 'Reports API', scopes: [new ApiScope('reports:read')], unlinkClient: true));
    $m->deleteApi('api_1');
    $m->apiKeys('org_1', clientId: 'cid_1');
    $m->revokeApiKey('key_1');
    $m->startSupportSession(new NewSupportSession('user_1', 'org_1', 'cid_1', 'staff_1', 'Ticket #42', 30, ['openid'], 'https://app.test/cb', str_repeat('a', 43), 'n-1'));

    $problems = [];

    foreach (Http::recorded() as [$request]) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $op = operationFor($request->method(), $path);
        $label = $request->method().' '.$path;

        if ($op === null) {
            $problems[] = "{$label}: no such operation in the spec";

            continue;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $declared = array_map(fn (array $p): string => resolveRef($p)['name'], $op[1]['parameters'] ?? []);

        foreach (array_keys($query) as $name) {
            if (! in_array($name, $declared, true)) {
                $problems[] = "{$label}: query parameter `{$name}` is not declared";
            }
        }

        if (in_array($request->method(), ['GET', 'DELETE'], true) || ! isset($op[1]['requestBody'])) {
            continue;
        }

        $schema = resolveRef($op[1]['requestBody']['content']['application/json']['schema']);
        $body = $request->data();

        foreach (array_keys($body) as $field) {
            if (! array_key_exists($field, $schema['properties'] ?? [])) {
                $problems[] = "{$label}: body field `{$field}` is not in the schema";
            }
        }

        foreach ($schema['required'] ?? [] as $field) {
            if (! array_key_exists($field, $body)) {
                $problems[] = "{$label}: required body field `{$field}` was not sent";
            }
        }
    }

    expect(Http::recorded())->toHaveCount(34)
        ->and($problems)->toBe([]);
});

it('reads every response the server documents', function (): void {
    $m = app(Management::class);

    $member = $m->transferOwnership('org_1', 'user_2');
    $invitation = $m->resendInvitation('org_1', 'inv_1');
    $role = $m->roles()[0];
    $assignment = $m->assignRole('org_1', 'user_1', 'role_1');
    $app = $m->apps()->items[0];
    $api = $m->api('api_1');
    $key = $m->apiKeys('org_1')->items[0];
    $session = $m->startSupportSession(new NewSupportSession('user_1', 'org_1', 'cid_1', 'staff_1', 'Ticket'));
    $organization = $m->archiveOrganization('org_1');
    $blueprint = $m->appBlueprint('cid_1');

    expect($member->userId)->toBe('user_id_1')
        ->and($member->membershipId)->toBe('id_1')
        ->and($member->role)->toBe(OrganizationRole::Owner)
        ->and($invitation->id)->toBe('id_1')
        ->and($invitation->invitedAt)->not->toBeNull()
        ->and($role->key)->toBe('key_1')
        ->and($role->organizationId)->toBe('organization_id_1')
        ->and($assignment->roleId)->toBe('role_id_1')
        ->and($assignment->source)->toBe('manual')
        ->and($app->clientType)->toBe('confidential')
        ->and($app->grantTypes)->toBe(['grant_types_1'])
        ->and($app->createdAt)->not->toBeNull()
        ->and($api->createdAt)->not->toBeNull()
        ->and($api->scopes[0]->key)->toBe('key_1')
        ->and($key->status)->toBe(ApiKeyStatus::Active)
        ->and($key->revokedAt)->not->toBeNull()
        ->and($session->actorId)->toBe('actor_id_1')
        ->and($session->code)->toBe('code_1')
        ->and($session->redirectUri)->toBe('redirect_uri_1')
        ->and($organization->createdAt)->not->toBeNull()
        ->and($blueprint->document['kind'])->toBe('cbox-id.client-blueprint');
});

it('reads a console-made role, which has no manifest key', function (): void {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['data' => [['id' => 'role_1', 'key' => null, 'name' => 'Auditor', 'tenant_assignable' => true, 'permissions' => []]]])]);

    expect(app(Management::class)->roles()[0]->key)->toBeNull();
});

it('sends an explicit null to unlink an API from its app, and nothing to leave it', function (): void {
    expect((new ApiChanges(unlinkClient: true))->toArray())->toBe(['client_id' => null])
        ->and((new ApiChanges(name: 'x'))->toArray())->toBe(['name' => 'x'])
        ->and(fn () => new ApiChanges(clientId: 'cid_1', unlinkClient: true))->toThrow(InvalidArgumentException::class);
});

it('addresses a role by manifest key with the app that declared it', function (): void {
    app(Management::class)->assignRole('org_1', 'user_1', 'editor', 'cid_1');

    Http::assertSent(fn (HttpRequest $r): bool => $r->method() === 'PUT'
        && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/organizations/org_1/members/user_1/roles/editor')
        && parse_url($r->url(), PHP_URL_QUERY) === 'client_id=cid_1');
});

it('creates an app from another environment\'s blueprint, overriding what names the old one', function (): void {
    $blueprint = new AppBlueprint('cid_1', ['kind' => 'cbox-id.client-blueprint', 'version' => 1, 'name' => 'Portal', 'client_type' => 'confidential']);

    expect(NewApp::fromBlueprint($blueprint, 'Portal (prod)', ['https://app.example/cb'])->toArray())->toBe([
        'blueprint' => $blueprint->document,
        'name' => 'Portal (prod)',
        'redirect_uris' => ['https://app.example/cb'],
    ]);
});
