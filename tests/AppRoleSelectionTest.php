<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Management\Data\AppRoleSelection;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\Data\Role;
use Cbox\Id\Client\Management\Data\RoleAssignment;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Testing\FakeManagement;
use Illuminate\Support\Facades\Http;

it('versions one app independently of the order of assignments and other apps', function (): void {
    $a = new RoleAssignment('r_a', 'reader', clientId: 'sites');
    $b = new RoleAssignment('r_b', 'billing', clientId: 'sites');
    $other = new RoleAssignment('r_c', 'admin', clientId: 'engage');

    $selection = AppRoleSelection::fromAssignments('sites', [$a, $other, $b]);
    expect($selection->keys())->toBe(['reader', 'billing'])
        ->and($selection->version())->toBe(AppRoleSelection::fromAssignments('sites', [$b, $a])->version());
});

it('replaces app access without changing organization membership or another app', function (): void {
    $fake = new FakeManagement;
    $org = $fake->createOrganization(new NewOrganization('Team', ownerUserId: 'owner'));
    $fake->addMember($org->id, 'person');
    $fake->withRole(new Role('reader_id', 'reader', clientId: 'sites'))
        ->withRole(new Role('billing_id', 'billing', clientId: 'sites'))
        ->withRole(new Role('other_id', 'editor', clientId: 'engage'));
    $fake->assignRole($org->id, 'person', 'other_id');
    $fake->assignRole($org->id, 'person', 'reader_id');
    $before = AppRoleSelection::fromAssignments('sites', $fake->memberRoles($org->id, 'person'));
    $after = $fake->replaceAppRoles($org->id, 'person', 'sites', ['reader', 'billing'], $before->version());

    expect(array_map(fn (RoleAssignment $role): string => $role->key, $after))->toBe(['reader', 'billing'])
        ->and(AppRoleSelection::fromAssignments('engage', $fake->memberRoles($org->id, 'person'))->keys())->toBe(['editor']);
    $fake->replaceAppRoles($org->id, 'person', 'sites', [], (new AppRoleSelection('sites', $after))->version());

    expect(AppRoleSelection::fromAssignments('sites', $fake->memberRoles($org->id, 'person'))->roles)->toBe([])
        ->and($fake->members($org->id)->items)->not->toBeEmpty();
    expect(fn () => $fake->replaceAppRoles($org->id, 'person', 'sites', ['reader'], $before->version()))->toThrow(ManagementApiException::class);
});

it('refuses an invalid app role before changing the existing selection', function (): void {
    $fake = new FakeManagement;
    $org = $fake->createOrganization(new NewOrganization('Team', ownerUserId: 'owner'));
    $fake->addMember($org->id, 'person');
    $fake->withRole(new Role('reader_id', 'reader', clientId: 'sites'))
        ->withRole(new Role('staff_id', 'support', clientId: 'sites', tenantAssignable: false));
    $fake->assignRole($org->id, 'person', 'reader_id');
    $selection = AppRoleSelection::fromAssignments('sites', $fake->memberRoles($org->id, 'person'));

    expect(fn () => $fake->replaceAppRoles($org->id, 'person', 'sites', ['support'], $selection->version()))->toThrow(ManagementApiException::class);
    expect(AppRoleSelection::fromAssignments('sites', $fake->memberRoles($org->id, 'person'))->keys())->toBe(['reader']);
});

it('sends the app and reviewed version with an atomic replacement', function (): void {
    config(['cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.management.key' => 'test-key']);
    Http::fake(['https://id.test/api/v1/organizations/org/members/person/apps/sites/roles' => Http::response(['data' => [
        ['role_id' => 'reader_id', 'key' => 'reader', 'client_id' => 'sites', 'organization_id' => 'org', 'user_id' => 'person'],
    ]])]);
    $version = (new AppRoleSelection('sites', []))->version();
    $roles = (new HttpManagementClient('https://id.test/api/v1', 'test-key'))->replaceAppRoles('org', 'person', 'sites', ['reader'], $version);

    expect($roles[0]->key)->toBe('reader');
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request['roles'] === ['reader'] && $request['version'] === $version);
});
