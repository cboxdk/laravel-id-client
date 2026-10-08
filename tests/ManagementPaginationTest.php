<?php

declare(strict_types=1);

use Cbox\Id\Client\Management\Environment\Schemas\Organization;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Workspace\Schemas\Environment;
use Cbox\Id\Client\Management\WorkspaceClient;

/*
 * Lists page two ways — an opaque `after` cursor on the environment plane, a `page`
 * number on the workspace plane — and every paged list has an `…All()` twin that walks
 * every page lazily.
 */

function organization(string $id): array
{
    return ['id' => $id, 'name' => "Org {$id}", 'slug' => $id, 'type' => 'customer', 'status' => 'active'];
}

it('answers one typed page, with its cursor', function (): void {
    fakeManagement(envelope([organization('org_1'), organization('org_2')], meta: ['limit' => 2, 'has_more' => true, 'next_cursor' => 'cur_2']));

    $page = envClient()->organizations->list(['limit' => 2]);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page)->toHaveCount(2)
        ->and($page->items[0])->toBeInstanceOf(Organization::class)
        ->and($page->items[1]->slug)->toBe('org_2')
        ->and($page->hasMore)->toBeTrue()
        ->and($page->nextCursor)->toBe('cur_2')
        ->and($page->nextPage)->toBeNull()
        ->and($page->response->meta['limit'])->toBe(2)
        ->and(iterator_to_array($page))->toBe($page->items);
});

it('walks every page by cursor, lazily', function (): void {
    $seen = fakeManagement(
        envelope([organization('org_1'), organization('org_2')], meta: ['has_more' => true, 'next_cursor' => 'cur_2']),
        envelope([organization('org_3')], meta: ['has_more' => false, 'next_cursor' => null]),
    );

    $all = envClient()->organizations->listAll(['status' => 'active']);

    expect($seen)->toHaveCount(0);

    $first = $all->current();
    expect($first->id)->toBe('org_1')->and($seen)->toHaveCount(1);

    $ids = [];

    foreach ($all as $organization) {
        $ids[] = $organization->id;
    }

    expect($ids)->toBe(['org_1', 'org_2', 'org_3'])
        ->and($seen)->toHaveCount(2)
        ->and($seen[0]->url())->toBe('https://acme.test/api/v1/organizations?status=active')
        ->and($seen[1]->url())->toBe('https://acme.test/api/v1/organizations?status=active&after=cur_2');
});

it('walks every page by number on the workspace plane', function (): void {
    $environment = fn (string $id): array => ['id' => $id, 'name' => $id, 'issuer' => "https://{$id}.cboxid.test"];
    $seen = fakeManagement(
        envelope([$environment('env_1')], meta: ['page' => 1, 'has_more' => true, 'next_page' => 2, 'total' => 2]),
        envelope([$environment('env_2')], meta: ['page' => 2, 'has_more' => false, 'total' => 2]),
    );

    $workspace = new WorkspaceClient(baseUrl: 'https://api.cboxid.test', apiKey: 'cbid_ws_test');
    $all = iterator_to_array($workspace->environments->listAll(), false);

    expect($all)->toHaveCount(2)
        ->and($all[1])->toBeInstanceOf(Environment::class)
        ->and($all[1]->issuer)->toBe('https://env_2.cboxid.test')
        ->and($seen[0]->url())->toBe('https://api.cboxid.test/api/v1/workspace/environments')
        ->and($seen[1]->url())->toBe('https://api.cboxid.test/api/v1/workspace/environments?page=2')
        ->and($seen[0]->header('Authorization'))->toBe(['Bearer cbid_ws_test']);
});

it('stops on an empty page even if the server says there is more', function (): void {
    $seen = fakeManagement(envelope([], meta: ['has_more' => true, 'next_cursor' => 'cur_x']));

    expect(iterator_to_array(envClient()->organizations->listAll()))->toBe([])
        ->and($seen)->toHaveCount(1);
});
