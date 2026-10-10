<?php

declare(strict_types=1);

use Cbox\Id\Client\Fga\FgaTuple;
use Cbox\Id\Client\Management\Environment\Schemas\FgaObject;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

/**
 * Feature flag evaluation and fine-grained authorization through the generated environment
 * client.
 */
function fgaCheck(bool $allowed = true): array
{
    return [
        'allowed' => $allowed,
        'resource_type' => 'document',
        'resource_id' => 'leave',
        'relation' => 'viewer',
        'subject' => ['type' => 'user', 'id' => 'alice', 'relation' => null],
        'consistency_token' => '7.9f3c1a7be2d0',
    ];
}

/** @return array<string, list<string>> */
function queryOf(HttpRequest $request): array
{
    $pairs = [];

    foreach (explode('&', (string) parse_url($request->url(), PHP_URL_QUERY)) as $pair) {
        [$key, $value] = array_map('urldecode', explode('=', $pair, 2) + [1 => '']);
        $pairs[$key][] = $value;
    }

    return $pairs;
}

it('evaluates every flag for a user in an organization', function (): void {
    $seen = fakeManagement(envelope([
        'user_id' => 'usr_1',
        'organization_id' => 'org_1',
        'feature_flags' => ['acme-beta'],
        'evaluations' => [
            ['key' => 'acme-beta', 'enabled' => true, 'reason' => 'organization_target'],
            ['key' => 'old-reports', 'enabled' => false, 'reason' => 'disabled'],
        ],
    ]));

    $result = envClient()->featureFlags->evaluate(['user_id' => 'usr_1', 'organization_id' => 'org_1'])->data;

    expect($seen[0]->url())->toBe('https://acme.test/api/v1/feature-flags/evaluate?user_id=usr_1&organization_id=org_1')
        ->and($result->featureFlags)->toBe(['acme-beta'])
        ->and($result->evaluations[1]->reason)->toBe('disabled');
});

it('writes tuples, then checks at least as fresh as the write', function (): void {
    $seen = fakeManagement(
        envelope(['written' => 1, 'deleted' => 0, 'consistency_token' => '7.9f']),
        envelope(fgaCheck()),
    );
    $env = envClient();

    $written = $env->fga->tuples->write(['tuples' => [[
        'resource_type' => 'group', 'resource_id' => 'eng', 'relation' => 'member',
        'subject' => ['type' => 'user', 'id' => 'alice'],
    ]]])->data;

    $check = $env->fga->check([
        'resource_type' => 'document', 'resource_id' => 'leave', 'relation' => 'viewer',
        'subject_type' => 'user', 'subject_id' => 'alice',
        'consistency_token' => $written->consistencyToken,
    ])->data;

    expect($seen[0]->method())->toBe('POST')
        ->and($seen[0]->url())->toBe('https://acme.test/api/v1/fga/tuples')
        ->and($seen[0]->hasHeader('Idempotency-Key'))->toBeTrue()
        ->and(queryOf($seen[1])['consistency_token'])->toBe(['7.9f'])
        ->and($check->allowed)->toBeTrue();
});

it('sends a batch as checks[] in the tuple notation', function (): void {
    $seen = fakeManagement(envelope(['results' => [fgaCheck(), fgaCheck(false)], 'consistency_token' => '7']));

    $batch = envClient()->fga->checkBatch(['checks' => [
        FgaTuple::check('document', 'leave', 'viewer', 'user', 'alice'),
        'document:readme#editor@user:alice',
    ]])->data;

    expect(parse_url($seen[0]->url(), PHP_URL_PATH))->toBe('/api/v1/fga/check/batch')
        ->and(queryOf($seen[0])['checks[]'])->toBe(['document:leave#viewer@user:alice', 'document:readme#editor@user:alice'])
        ->and(array_map(fn ($r) => $r->allowed, $batch->results))->toBe([true, false]);
});

it('deletes tuples, lists resources and subjects, and reads and replaces the schema', function (): void {
    $schema = ['defined' => true, 'schema' => 'type user', 'version' => 2, 'types' => [], 'updated_at' => null, 'consistency_token' => '8.b'];
    $page = ['has_more' => false, 'next_cursor' => null];
    $seen = fakeManagement(
        envelope(['written' => 0, 'deleted' => 1, 'consistency_token' => '8.a']),
        envelope([['type' => 'document', 'id' => 'leave']], meta: $page),
        envelope([['type' => 'user', 'id' => 'alice']], meta: $page),
        envelope($schema),
        envelope($schema),
    );
    $env = envClient();
    $tuple = ['resource_type' => 'group', 'resource_id' => 'eng', 'relation' => 'member', 'subject' => ['type' => 'user', 'id' => 'alice']];

    expect($env->fga->tuples->delete(['tuples' => [$tuple]])->data->deleted)->toBe(1);
    $resources = $env->fga->resources->list(['resource_type' => 'document', 'relation' => 'viewer', 'subject_type' => 'user', 'subject_id' => 'alice']);
    $subjects = $env->fga->subjects->list(['resource_type' => 'document', 'resource_id' => 'leave', 'relation' => 'viewer', 'subject_type' => 'user', 'consistency_token' => '8.a']);
    $env->fga->schema->get();
    $env->fga->schema->update(['schema' => 'type user']);

    expect(array_map(fn (HttpRequest $r): string => $r->method().' '.parse_url($r->url(), PHP_URL_PATH), $seen->getArrayCopy()))->toBe([
        'POST /api/v1/fga/tuples/delete',
        'GET /api/v1/fga/resources',
        'GET /api/v1/fga/subjects',
        'GET /api/v1/fga/schema',
        'PUT /api/v1/fga/schema',
    ])
        ->and(queryOf($seen[2])['consistency_token'])->toBe(['8.a'])
        ->and($seen[4]->data())->toBe(['schema' => 'type user'])
        ->and($resources->items[0])->toBeInstanceOf(FgaObject::class)
        ->and($resources->items[0]->id)->toBe('leave')
        ->and($subjects->items[0]->id)->toBe('alice');
});

it('writes the tuple notation for one subject and a userset', function (): void {
    expect(FgaTuple::format([
        'resource_type' => 'folder', 'resource_id' => 'policies', 'relation' => 'viewer',
        'subject' => ['type' => 'group', 'id' => 'eng', 'relation' => 'member'],
    ]))->toBe('folder:policies#viewer@group:eng#member')
        // Ids are the app's own and may carry a colon.
        ->and(FgaTuple::check('doc', 'a:b', 'viewer', 'user', 'u:1'))->toBe('doc:a:b#viewer@user:u:1');
});

it('refuses a part the notation cannot carry', function (string $type, string $id, string $subject): void {
    FgaTuple::check($type, $id, 'viewer', 'user', $subject);
})->throws(InvalidArgumentException::class)->with([
    ['doc', 'a#b', 'x'],
    ['doc', 'a', 'x@y'],
    ['doc:x', 'a', 'x'],
    ['doc', '', 'x'],
]);

it('reads the own 202 body of an action that answers Accepted, not as an approval', function (): void {
    $seen = fakeManagement(Http::response(['data' => [
        'id' => 'dir_1', 'organization_id' => 'org_1', 'name' => 'Workday', 'provider' => 'workday',
        'pull' => true, 'active' => true, 'status' => 'active',
    ]], 202));

    $result = envClient()->directories->sync('dir_1', ['full' => true]);

    expect($seen)->toHaveCount(1)
        ->and($result->status)->toBe(202)
        ->and($result->data->id)->toBe('dir_1');
});
