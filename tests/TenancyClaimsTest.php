<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\ValueObjects\CboxUser;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\VerifiedToken;

/**
 * The tenancy and authorization questions, asked of every kind of principal.
 *
 * The claims are the ones Cbox ID mints — `org`, `org_name`, `org_role`, `roles`,
 * `permissions`, `act` — and every answer is deny-by-default: a malformed claim is
 * absent, never coerced into something that grants.
 */
function userWith(array $claims): CboxUser
{
    return new CboxUser('user_1', null, null, is_string($claims['org'] ?? null) ? $claims['org'] : null, $claims, 'at', null, null, 300);
}

function tokenWith(array $claims): VerifiedToken
{
    return new VerifiedToken('user_1', 'cid', is_string($claims['org'] ?? null) ? $claims['org'] : null, null, 'https://api', [], [], 'jti', time() + 60, $claims);
}

function principalOf(string $kind, array $claims): Principal
{
    return match ($kind) {
        'user' => userWith($claims),
        'token' => tokenWith($claims),
        default => Identity::fromClaims(['sub' => 'user_1'] + $claims) ?? throw new LogicException,
    };
}

dataset('principals', [
    'a signed-in user' => ['user'],
    'a verified token' => ['token'],
    'the session identity' => ['identity'],
]);

it('reads the organization, its name and the member tier', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    $principal = $make(['org' => 'org_1', 'org_name' => 'Acme', 'org_role' => 'admin']);

    $organization = $principal->organization();

    expect($organization?->id)->toBe('org_1')
        ->and($organization?->name)->toBe('Acme')
        ->and($organization?->role)->toBe(OrganizationRole::Admin)
        ->and($organization?->canManage())->toBeTrue()
        ->and($organization?->isOwner())->toBeFalse()
        ->and($organization?->roleAtLeast(OrganizationRole::Developer))->toBeTrue()
        ->and($organization?->roleAtLeast(OrganizationRole::Owner))->toBeFalse();
})->with('principals');

it('acts for no organization when `org` is absent or not a string', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    expect($make([])->organization())->toBeNull()
        ->and($make(['org' => ['org_1']])->organization())->toBeNull()
        ->and($make(['org' => ''])->organization())->toBeNull();
})->with('principals');

it('treats an unknown tier as no tier, which is never enough', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    $organization = $make(['org' => 'org_1', 'org_role' => 'superuser'])->organization();

    expect($organization?->role)->toBeNull()
        ->and($organization?->canManage())->toBeFalse()
        ->and($organization?->roleAtLeast(OrganizationRole::Viewer))->toBeFalse();
})->with('principals');

it('answers permissions and roles by exact match only', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    $principal = $make(['roles' => ['billing-admin'], 'permissions' => ['invoices:create', 'invoices:read']]);

    expect($principal->permissions())->toBe(['invoices:create', 'invoices:read'])
        ->and($principal->hasPermission('invoices:create'))->toBeTrue()
        ->and($principal->hasPermission('invoices:delete'))->toBeFalse()
        // No wildcard expansion: the issuer resolved permissions before it signed.
        ->and($principal->hasPermission('invoices:*'))->toBeFalse()
        ->and($principal->hasPermission(''))->toBeFalse()
        ->and($principal->hasRole('billing-admin'))->toBeTrue()
        ->and($principal->hasRole('admin'))->toBeFalse();
})->with('principals');

it('ignores permission entries that are not strings', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    $principal = $make(['permissions' => [['invoices:create'], 42, null, 'invoices:read']]);

    expect($principal->permissions())->toBe(['invoices:read'])
        ->and($principal->hasPermission('invoices:create'))->toBeFalse();
})->with('principals');

it('recognises a support session by its act claim', function (string $kind): void {
    $make = fn (array $claims): Principal => principalOf($kind, $claims);
    $support = $make(['act' => ['sub' => 'staff_9']]);
    $normal = $make([]);

    expect($support->isSupportSession())->toBeTrue()
        ->and($support->actor()?->subject)->toBe('staff_9')
        ->and($normal->isSupportSession())->toBeFalse()
        ->and($normal->actor())->toBeNull()
        // An act without a subject names nobody, so it is not a support session.
        ->and($make(['act' => ['client_id' => 'x']])->isSupportSession())->toBeFalse();
})->with('principals');

it('lists every organization from the organizations claim, for a switcher', function (): void {
    $user = userWith(['organizations' => [
        ['id' => 'org_1', 'name' => 'Acme', 'role' => 'owner'],
        ['id' => 'org_2', 'name' => 'Globex', 'role' => 'viewer'],
        ['name' => 'no id'],
        'garbage',
    ]]);

    $organizations = $user->organizations();

    expect($organizations)->toHaveCount(2)
        ->and($organizations[0]->id)->toBe('org_1')
        ->and($organizations[0]->role)->toBe(OrganizationRole::Owner)
        ->and($organizations[1]->label())->toBe('Globex');
});

it('keeps only the allow-listed claims when it remembers an identity', function (): void {
    $identity = Identity::fromPrincipal(userWith([
        'sub' => 'user_1', 'org' => 'org_1', 'permissions' => ['a:b'],
        'at_hash' => 'x', 'ent_seats' => 5, 'custom_pii' => 'secret',
    ]));

    expect($identity->toArray())->toHaveKeys(['sub', 'org', 'permissions'])
        ->and($identity->toArray())->not->toHaveKey('at_hash')
        ->and($identity->toArray())->not->toHaveKey('ent_seats')
        ->and($identity->toArray())->not->toHaveKey('custom_pii');
});

it('orders the organization tiers the way Cbox ID does', function (): void {
    expect(OrganizationRole::Owner->atLeast(OrganizationRole::Admin))->toBeTrue()
        ->and(OrganizationRole::Member->atLeast(OrganizationRole::Developer))->toBeFalse()
        ->and(OrganizationRole::Viewer->canWrite())->toBeFalse()
        ->and(OrganizationRole::Developer->canManageOrganization())->toBeFalse();
});
