<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Enums\AssignableMemberRole;
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

    /**
     * `organizations:write` — archives rather than erases: status becomes `deleted`, every
     * member loses access, the rows stay for the audit trail. Idempotent.
     */
    public function archiveOrganization(string $organizationId): Organization;

    /** @return Page<Member> `members:read` */
    public function members(string $organizationId, ?string $after = null, int $limit = 50): Page;

    /**
     * `members:write` — an existing user, straight in (no invitation). Idempotent for the
     * same role; a member on a different role is `409 already_member`.
     */
    public function addMember(string $organizationId, string $userId, AssignableMemberRole $role = AssignableMemberRole::Member): Member;

    /** `members:write` — `admin` or `member`. Demoting the only owner is `409 last_owner`. */
    public function updateMember(string $organizationId, string $userId, AssignableMemberRole $role): Member;

    /** `members:write` — refused for the only owner (`409 last_owner`); transfer first. */
    public function removeMember(string $organizationId, string $userId): void;

    /**
     * `organizations:write` — make an active member the owner; the previous owner stays on
     * as `admin`. Returns the new owner's membership.
     */
    public function transferOwnership(string $organizationId, string $userId): Member;

    /** @return Page<Invitation> `invitations:read` — pending and unexpired. */
    public function invitations(string $organizationId, ?string $after = null, int $limit = 50): Page;

    /** `invitations:write` */
    public function invite(string $organizationId, NewInvitation $invitation): Invitation;

    /** `invitations:write` */
    public function revokeInvitation(string $organizationId, string $invitationId): void;

    /**
     * `invitations:write` — mails a FRESH link. The answer is a NEW invitation whose `id`
     * replaces the old one, which stops working.
     */
    public function resendInvitation(string $organizationId, string $invitationId): Invitation;

    /** @return list<RoleAssignment> `roles:read` — roles granted AT this organization. */
    public function memberRoles(string $organizationId, string $userId): array;

    /**
     * `roles:write` — idempotent. `$roleId` is a role id, or a manifest key together with
     * `$clientId` naming the app that declared it. With the environment's authority, so a
     * staff role can be granted here.
     */
    public function assignRole(string $organizationId, string $userId, string $roleId, ?string $clientId = null): RoleAssignment;

    /** `roles:write` — idempotent. */
    public function unassignRole(string $organizationId, string $userId, string $roleId, ?string $clientId = null): void;

    /**
     * @return list<Role> `roles:read` — every grantable role, narrowed to one app with
     *                    `$clientId`, or to what can be granted in one organization.
     */
    public function roles(?string $clientId = null, ?string $organizationId = null): array;

    /** @return list<RoleAssignment> `roles:read` — the roles a user holds everywhere (staff). */
    public function environmentRoles(string $userId): array;

    /** `roles:read` — whether a user holds a role environment-wide. */
    public function hasEnvironmentRole(string $userId, string $roleId, ?string $clientId = null): bool;

    /** `roles:write` — grant a role everywhere in the environment: staff. Idempotent. */
    public function grantEnvironmentRole(string $userId, string $roleId, ?string $clientId = null): RoleAssignment;

    /** `roles:write` — idempotent. */
    public function revokeEnvironmentRole(string $userId, string $roleId, ?string $clientId = null): void;

    /** @return Page<App> `apps:read` — never a secret. */
    public function apps(?string $after = null, int $limit = 50): Page;

    /** `apps:write` — the response carries the client secret, once. */
    public function createApp(NewApp $app): App;

    /** `apps:read` — `$appId` is the app's id or its client id. */
    public function appBlueprint(string $appId): AppBlueprint;

    /** @return Page<Api> `apis:read` */
    public function apis(?string $after = null, int $limit = 50): Page;

    /** `apis:read` */
    public function api(string $apiId): Api;

    /** `apis:write` */
    public function createApi(NewApi $api): Api;

    /** `apis:write` — `scopes` replaces the whole set; see {@see ApiChanges}. */
    public function updateApi(string $apiId, ApiChanges $changes): Api;

    /** `apis:write` */
    public function deleteApi(string $apiId): void;

    /** @return Page<ApiKey> `api_keys:read` — revoked and expired included; narrow with `$clientId`. */
    public function apiKeys(string $organizationId, ?string $after = null, int $limit = 50, ?string $clientId = null): Page;

    /** `api_keys:write` — idempotent. */
    public function revokeApiKey(string $apiKeyId): void;

    /** `support:write` */
    public function startSupportSession(NewSupportSession $session): SupportSession;
}
