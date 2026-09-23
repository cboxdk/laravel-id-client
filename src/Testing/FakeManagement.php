<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Management\Data\Api;
use Cbox\Id\Client\Management\Data\ApiChanges;
use Cbox\Id\Client\Management\Data\ApiKey;
use Cbox\Id\Client\Management\Data\App;
use Cbox\Id\Client\Management\Data\AppBlueprint;
use Cbox\Id\Client\Management\Data\Invitation;
use Cbox\Id\Client\Management\Data\Member;
use Cbox\Id\Client\Management\Data\NewApi;
use Cbox\Id\Client\Management\Data\NewApp;
use Cbox\Id\Client\Management\Data\NewInvitation;
use Cbox\Id\Client\Management\Data\NewOrganization;
use Cbox\Id\Client\Management\Data\NewSupportSession;
use Cbox\Id\Client\Management\Data\Organization;
use Cbox\Id\Client\Management\Data\OrganizationChanges;
use Cbox\Id\Client\Management\Data\Page;
use Cbox\Id\Client\Management\Data\Role;
use Cbox\Id\Client\Management\Data\RoleAssignment;
use Cbox\Id\Client\Management\Data\SupportSession;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * The environment management API, in memory, recording every call.
 *
 *     $cbox = CboxId::fake()->management();
 *
 *     $this->post('/teams/org_1/invite', ['email' => 'ada@example.com']);
 *
 *     $cbox->assertInvited('ada@example.com', 'org_1');
 *     $cbox->assertCalled('assignRole', fn (array $args) => $args[2] === 'role_editor');
 *
 * It keeps enough state to be read back — create an organization, then list it — and
 * refuses what the real API refuses where a test is likely to rely on it: an unknown
 * organization is a {@see ResourceNotFound}. `failNext()` makes the next call to a method
 * throw whatever you give it, for testing your error handling.
 */
class FakeManagement implements Management
{
    /** @var list<array{method: string, args: list<mixed>}> */
    private array $calls = [];

    /** @var array<string, Organization> */
    private array $organizations = [];

    /** @var array<string, array<string, Member>> */
    private array $members = [];

    /** @var array<string, array<string, Invitation>> */
    private array $invitations = [];

    /** @var array<string, list<string>> keyed "org|user" */
    private array $assignments = [];

    /** @var array<string, list<string>> keyed by user */
    private array $environmentRoles = [];

    /** @var array<string, Role> */
    private array $roles = [];

    /** @var array<string, App> */
    private array $apps = [];

    /** @var array<string, Api> */
    private array $apis = [];

    /** @var array<string, ApiKey> */
    private array $apiKeys = [];

    /** @var array<string, ManagementApiException> */
    private array $failures = [];

    private int $sequence = 0;

    // ---- seeding & failure injection -------------------------------------------------

    public function withOrganization(Organization $organization): static
    {
        $this->organizations[$organization->id] = $organization;

        return $this;
    }

    public function withRole(Role $role): static
    {
        $this->roles[$role->id] = $role;

        return $this;
    }

    public function withApiKey(ApiKey $key): static
    {
        $this->apiKeys[$key->id] = $key;

        return $this;
    }

    /** The next call to `$method` throws `$exception` (recorded all the same). */
    public function failNext(string $method, ManagementApiException $exception): static
    {
        $this->failures[$method] = $exception;

        return $this;
    }

    // ---- Management ------------------------------------------------------------------

    public function organizations(?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$after, $limit]);

        return new Page(array_values($this->organizations));
    }

    public function organization(string $organizationId): Organization
    {
        $this->record(__FUNCTION__, [$organizationId]);

        return $this->findOrganization($organizationId);
    }

    public function createOrganization(NewOrganization $organization): Organization
    {
        $this->record(__FUNCTION__, [$organization]);

        $created = new Organization(
            id: $this->id('org'),
            name: $organization->name,
            slug: $organization->slug,
            type: 'customer',
            status: 'active',
            parentId: $organization->parentId,
        );
        $this->organizations[$created->id] = $created;

        if ($organization->ownerUserId !== null) {
            $this->members[$created->id][$organization->ownerUserId] = new Member($organization->ownerUserId, OrganizationRole::Owner, $created->id);
        }

        return $created;
    }

    public function updateOrganization(string $organizationId, OrganizationChanges $changes): Organization
    {
        $this->record(__FUNCTION__, [$organizationId, $changes]);
        $current = $this->findOrganization($organizationId);

        return $this->organizations[$organizationId] = new Organization(
            $current->id,
            $changes->name ?? $current->name,
            $changes->slug ?? $current->slug,
            $current->type,
            $current->status,
            $current->parentId,
        );
    }

    public function archiveOrganization(string $organizationId): void
    {
        $this->record(__FUNCTION__, [$organizationId]);
        $current = $this->findOrganization($organizationId);

        $this->organizations[$organizationId] = new Organization($current->id, $current->name, $current->slug, $current->type, 'archived', $current->parentId);
    }

    public function members(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$organizationId, $after, $limit]);

        return new Page(array_values($this->members[$organizationId] ?? []));
    }

    public function addMember(string $organizationId, string $userId, OrganizationRole $role = OrganizationRole::Member): Member
    {
        $this->record(__FUNCTION__, [$organizationId, $userId, $role]);

        return $this->members[$organizationId][$userId] = new Member($userId, $role, $organizationId);
    }

    public function updateMember(string $organizationId, string $userId, OrganizationRole $role): Member
    {
        $this->record(__FUNCTION__, [$organizationId, $userId, $role]);
        $this->findMember($organizationId, $userId);

        return $this->members[$organizationId][$userId] = new Member($userId, $role, $organizationId);
    }

    public function removeMember(string $organizationId, string $userId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $userId]);
        $this->findMember($organizationId, $userId);

        unset($this->members[$organizationId][$userId]);
    }

    public function transferOwnership(string $organizationId, string $userId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $userId]);
        $this->findMember($organizationId, $userId);

        foreach ($this->members[$organizationId] ?? [] as $id => $member) {
            if ($member->role === OrganizationRole::Owner) {
                $this->members[$organizationId][$id] = new Member($id, OrganizationRole::Admin, $organizationId);
            }
        }

        $this->members[$organizationId][$userId] = new Member($userId, OrganizationRole::Owner, $organizationId);
    }

    public function invitations(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$organizationId, $after, $limit]);

        return new Page(array_values($this->invitations[$organizationId] ?? []));
    }

    public function invite(string $organizationId, NewInvitation $invitation): Invitation
    {
        $this->record(__FUNCTION__, [$organizationId, $invitation]);

        $created = new Invitation(
            id: $this->id('inv'),
            email: $invitation->email,
            role: $invitation->role,
            roles: $invitation->roles,
            organizationId: $organizationId,
            status: 'pending',
            returnTo: $invitation->returnTo,
            clientId: $invitation->clientId,
        );

        return $this->invitations[$organizationId][$created->id] = $created;
    }

    public function revokeInvitation(string $organizationId, string $invitationId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $invitationId]);
        $this->findInvitation($organizationId, $invitationId);

        unset($this->invitations[$organizationId][$invitationId]);
    }

    public function resendInvitation(string $organizationId, string $invitationId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $invitationId]);
        $this->findInvitation($organizationId, $invitationId);
    }

    public function memberRoles(string $organizationId, string $userId): array
    {
        $this->record(__FUNCTION__, [$organizationId, $userId]);

        return array_map(
            fn (string $roleId): RoleAssignment => new RoleAssignment($roleId, $this->roles[$roleId]->key ?? null, $this->roles[$roleId]->name ?? null, $organizationId, $userId),
            $this->assignments[$organizationId.'|'.$userId] ?? [],
        );
    }

    public function assignRole(string $organizationId, string $userId, string $roleId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $userId, $roleId]);
        $key = $organizationId.'|'.$userId;

        if (! in_array($roleId, $this->assignments[$key] ?? [], true)) {
            $this->assignments[$key][] = $roleId;
        }
    }

    public function unassignRole(string $organizationId, string $userId, string $roleId): void
    {
        $this->record(__FUNCTION__, [$organizationId, $userId, $roleId]);
        $key = $organizationId.'|'.$userId;

        $this->assignments[$key] = array_values(array_diff($this->assignments[$key] ?? [], [$roleId]));
    }

    public function roles(): array
    {
        $this->record(__FUNCTION__, []);

        return array_values($this->roles);
    }

    public function hasEnvironmentRole(string $userId, string $roleId): bool
    {
        $this->record(__FUNCTION__, [$userId, $roleId]);

        return in_array($roleId, $this->environmentRoles[$userId] ?? [], true);
    }

    public function grantEnvironmentRole(string $userId, string $roleId): void
    {
        $this->record(__FUNCTION__, [$userId, $roleId]);

        if (! in_array($roleId, $this->environmentRoles[$userId] ?? [], true)) {
            $this->environmentRoles[$userId][] = $roleId;
        }
    }

    public function revokeEnvironmentRole(string $userId, string $roleId): void
    {
        $this->record(__FUNCTION__, [$userId, $roleId]);

        $this->environmentRoles[$userId] = array_values(array_diff($this->environmentRoles[$userId] ?? [], [$roleId]));
    }

    public function apps(?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$after, $limit]);

        return new Page(array_values($this->apps));
    }

    public function createApp(NewApp $app): App
    {
        $this->record(__FUNCTION__, [$app]);
        $id = $this->id('cid');

        return $this->apps[$id] = new App($id, $id, $app->name, $app->type, $app->redirectUris, 'csec_fake_'.$id);
    }

    public function appBlueprint(string $appId): AppBlueprint
    {
        $this->record(__FUNCTION__, [$appId]);
        $app = $this->apps[$appId] ?? throw $this->notFound('App');

        return new AppBlueprint($appId, ['name' => $app->name, 'type' => $app->type, 'redirect_uris' => $app->redirectUris]);
    }

    public function apis(?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$after, $limit]);

        return new Page(array_values($this->apis));
    }

    public function createApi(NewApi $api): Api
    {
        $this->record(__FUNCTION__, [$api]);
        $id = $this->id('api');

        return $this->apis[$id] = new Api($id, $api->identifier, $api->name, $api->organizationId, $api->clientId, $api->scopes);
    }

    public function updateApi(string $apiId, ApiChanges $changes): Api
    {
        $this->record(__FUNCTION__, [$apiId, $changes]);
        $current = $this->apis[$apiId] ?? throw $this->notFound('API');

        return $this->apis[$apiId] = new Api(
            $current->id,
            $current->identifier,
            $changes->name ?? $current->name,
            $current->organizationId,
            $changes->clientId ?? $current->clientId,
            $changes->scopes ?? $current->scopes,
        );
    }

    public function deleteApi(string $apiId): void
    {
        $this->record(__FUNCTION__, [$apiId]);

        if (! isset($this->apis[$apiId])) {
            throw $this->notFound('API');
        }

        unset($this->apis[$apiId]);
    }

    public function apiKeys(string $organizationId, ?string $after = null, int $limit = 50): Page
    {
        $this->record(__FUNCTION__, [$organizationId, $after, $limit]);

        return new Page(array_values(array_filter($this->apiKeys, static fn (ApiKey $k): bool => $k->organizationId === $organizationId)));
    }

    public function revokeApiKey(string $apiKeyId): void
    {
        $this->record(__FUNCTION__, [$apiKeyId]);
        $key = $this->apiKeys[$apiKeyId] ?? throw $this->notFound('API key');

        $this->apiKeys[$apiKeyId] = new ApiKey($key->id, $key->name, $key->prefix, $key->organizationId, $key->userId, $key->clientId, $key->permissions, $key->createdAt, $key->expiresAt, $key->lastUsedAt, true);
    }

    public function startSupportSession(NewSupportSession $session): SupportSession
    {
        $this->record(__FUNCTION__, [$session]);

        return new SupportSession(
            id: $this->id('sup'),
            userId: $session->userId,
            organizationId: $session->organizationId,
            clientId: $session->clientId,
            actorSubject: $session->actorUserId,
            reason: $session->reason,
            expiresAt: (new \DateTimeImmutable)->modify('+'.min(60, $session->ttlMinutes ?? 60).' minutes'),
        );
    }

    // ---- assertions ------------------------------------------------------------------

    /**
     * The arguments of every call to `$method`, in order.
     *
     * @return list<list<mixed>>
     */
    public function recorded(string $method): array
    {
        $out = [];

        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                $out[] = $call['args'];
            }
        }

        return $out;
    }

    /**
     * @param  (Closure(list<mixed>): bool)|null  $where  receives the call's arguments
     */
    public function assertCalled(string $method, ?Closure $where = null, ?int $times = null): void
    {
        $matching = array_filter($this->recorded($method), static fn (array $args): bool => $where === null || $where($args));

        if ($times !== null) {
            Assert::assertCount($times, $matching, "Expected [{$method}] to be called {$times} time(s) as described; it was called ".count($matching).'.');

            return;
        }

        Assert::assertNotEmpty($matching, "Expected [{$method}] to be called as described; it was not.");
    }

    /**
     * @param  (Closure(list<mixed>): bool)|null  $where
     */
    public function assertNotCalled(string $method, ?Closure $where = null): void
    {
        $matching = array_filter($this->recorded($method), static fn (array $args): bool => $where === null || $where($args));

        Assert::assertEmpty($matching, "Expected [{$method}] not to be called as described; it was.");
    }

    public function assertNothingCalled(): void
    {
        Assert::assertSame([], array_column($this->calls, 'method'), 'The management API was called unexpectedly.');
    }

    /** @param (Closure(NewOrganization): bool)|null $where */
    public function assertOrganizationCreated(?Closure $where = null): void
    {
        $this->assertCalled('createOrganization', static fn (array $args): bool => $args[0] instanceof NewOrganization && ($where === null || $where($args[0])));
    }

    public function assertInvited(string $email, ?string $organizationId = null): void
    {
        $this->assertCalled('invite', static fn (array $args): bool => $args[1] instanceof NewInvitation
            && strcasecmp($args[1]->email, $email) === 0
            && ($organizationId === null || $args[0] === $organizationId));
    }

    public function assertMemberAdded(string $organizationId, string $userId, ?OrganizationRole $role = null): void
    {
        $this->assertCalled('addMember', static fn (array $args): bool => $args[0] === $organizationId && $args[1] === $userId && ($role === null || $args[2] === $role));
    }

    public function assertRoleAssigned(string $organizationId, string $userId, string $roleId): void
    {
        $this->assertCalled('assignRole', static fn (array $args): bool => $args === [$organizationId, $userId, $roleId]);
    }

    public function assertApiKeyRevoked(string $apiKeyId): void
    {
        $this->assertCalled('revokeApiKey', static fn (array $args): bool => $args === [$apiKeyId]);
    }

    /** @param (Closure(NewSupportSession): bool)|null $where */
    public function assertSupportSessionStarted(?Closure $where = null): void
    {
        $this->assertCalled('startSupportSession', static fn (array $args): bool => $args[0] instanceof NewSupportSession && ($where === null || $where($args[0])));
    }

    // ---- internals -------------------------------------------------------------------

    /** @param list<mixed> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        if (isset($this->failures[$method])) {
            $exception = $this->failures[$method];
            unset($this->failures[$method]);

            throw $exception;
        }
    }

    private function findOrganization(string $id): Organization
    {
        return $this->organizations[$id] ?? throw $this->notFound('Organization');
    }

    private function findMember(string $organizationId, string $userId): Member
    {
        $this->findOrganization($organizationId);

        return $this->members[$organizationId][$userId] ?? throw $this->notFound('Member');
    }

    private function findInvitation(string $organizationId, string $invitationId): Invitation
    {
        return $this->invitations[$organizationId][$invitationId] ?? throw $this->notFound('Invitation');
    }

    private function notFound(string $what): ResourceNotFound
    {
        $exception = new ResourceNotFound($what.' not found.');
        $exception->error = 'not_found';
        $exception->status = 404;

        return $exception;
    }

    private function id(string $prefix): string
    {
        return $prefix.'_fake_'.(++$this->sequence);
    }
}
