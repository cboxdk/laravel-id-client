<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ManagementApiException;
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
use Cbox\Id\Client\Management\HttpManagementClient;

/**
 * The environment management API — provisioning inside ONE Cbox ID environment, as your
 * application, with an environment API key (`cbid_env_…`).
 *
 * Each method names the scope its endpoint requires; a key without it gets a 403
 * ({@see ManagementApiException::isForbidden()}). Bound to
 * {@see HttpManagementClient}; `CboxId::fake()->management()`
 * swaps in an in-memory one that records every call for assertions.
 *
 * Every method throws {@see ManagementApiException} (or its `ResourceNotFound` /
 * `ValidationFailed` subclasses) on a refusal.
 */
interface Management
{
    /** @return Page<Organization> `organizations:read` */
    public function organizations(?string $after = null, int $limit = 50): Page;

    /** `organizations:read` */
    public function organization(string $organizationId): Organization;

    /** `organizations:write` — name `ownerUserId` to create it with its Owner. */
    public function createOrganization(NewOrganization $organization): Organization;

    /** `organizations:write` */
    public function updateOrganization(string $organizationId, OrganizationChanges $changes): Organization;

    /** `organizations:write` — `DELETE /v1/organizations/{id}`: archive the organization. */
    public function archiveOrganization(string $organizationId): void;

    /** @return Page<Member> `members:read` */
    public function members(string $organizationId, ?string $after = null, int $limit = 50): Page;

    /** `members:write` — an existing user, straight in (no invitation). */
    public function addMember(string $organizationId, string $userId, OrganizationRole $role = OrganizationRole::Member): Member;

    /** `members:write` — change the tier. Making someone Owner is {@see transferOwnership()}. */
    public function updateMember(string $organizationId, string $userId, OrganizationRole $role): Member;

    /** `members:write` — refused for the last Owner; {@see transferOwnership()} first. */
    public function removeMember(string $organizationId, string $userId): void;

    /** `members:write` — hand ownership to another member of the organization. */
    public function transferOwnership(string $organizationId, string $userId): void;

    /** @return Page<Invitation> `invitations:read` */
    public function invitations(string $organizationId, ?string $after = null, int $limit = 50): Page;

    /** `invitations:write` */
    public function invite(string $organizationId, NewInvitation $invitation): Invitation;

    /** `invitations:write` */
    public function revokeInvitation(string $organizationId, string $invitationId): void;

    /** `invitations:write` */
    public function resendInvitation(string $organizationId, string $invitationId): void;

    /** @return list<RoleAssignment> `roles:read` — this member's app roles in the organization. */
    public function memberRoles(string $organizationId, string $userId): array;

    /** `roles:write` — idempotent. Staff-only roles are refused inside an organization. */
    public function assignRole(string $organizationId, string $userId, string $roleId): void;

    /** `roles:write` — idempotent. */
    public function unassignRole(string $organizationId, string $userId, string $roleId): void;

    /** @return list<Role> `roles:read` — every role in the environment. */
    public function roles(): array;

    /** `roles:read` — whether a person holds a role environment-wide (staff). */
    public function hasEnvironmentRole(string $userId, string $roleId): bool;

    /** `roles:write` — grant a role everywhere in the environment: staff. */
    public function grantEnvironmentRole(string $userId, string $roleId): void;

    /** `roles:write` */
    public function revokeEnvironmentRole(string $userId, string $roleId): void;

    /** @return Page<App> `apps:read` */
    public function apps(?string $after = null, int $limit = 50): Page;

    /** `apps:write` — the response carries the client secret, once. */
    public function createApp(NewApp $app): App;

    /** `apps:read` — portable configuration, to promote an app to another environment. */
    public function appBlueprint(string $appId): AppBlueprint;

    /** @return Page<Api> `apis:read` */
    public function apis(?string $after = null, int $limit = 50): Page;

    /** `apis:write` */
    public function createApi(NewApi $api): Api;

    /** `apis:write` */
    public function updateApi(string $apiId, ApiChanges $changes): Api;

    /** `apis:write` */
    public function deleteApi(string $apiId): void;

    /** @return Page<ApiKey> `api_keys:read` */
    public function apiKeys(string $organizationId, ?string $after = null, int $limit = 50): Page;

    /** `api_keys:write` */
    public function revokeApiKey(string $apiKeyId): void;

    /** `support:write` */
    public function startSupportSession(NewSupportSession $session): SupportSession;
}
