<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

/**
 * Cbox ID's webhook event names, so a handler is registered against a constant rather
 * than a string that can be misspelt into a handler that never runs:
 *
 *     CboxIdWebhooks::on(EventType::MembershipCreated, fn (WebhookEvent $e) => …);
 *
 * Mirrors the server's catalogue. It is a convenience, not an allow-list: Cbox ID emits
 * more than this (plugins add their own), and `on()` still takes any string.
 */
enum EventType: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';
    case UserLogin = 'user.login';
    case IdentityLinked = 'identity.linked';

    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationDeleted = 'organization.deleted';
    case OrganizationArchived = 'organization.archived';
    case OrganizationSuspended = 'organization.suspended';
    case OrganizationReactivated = 'organization.reactivated';
    case OrganizationSettingsUpdated = 'organization.settings_updated';

    case OrganizationMemberAdded = 'organization.member_added';
    case OrganizationMemberRemoved = 'organization.member_removed';
    case OrganizationMemberRoleChanged = 'organization.member_role_changed';
    case OrganizationInvitationCreated = 'organization.invitation_created';
    case OrganizationInvitationAccepted = 'organization.invitation_accepted';

    case MembershipCreated = 'membership.created';
    case MembershipUpdated = 'membership.updated';
    case MembershipDeleted = 'membership.deleted';

    case InvitationCreated = 'invitation.created';
    case InvitationAccepted = 'invitation.accepted';
    case InvitationRevoked = 'invitation.revoked';

    case RoleAssigned = 'role.assigned';
    case RoleUnassigned = 'role.unassigned';
    case RoleAssignedEverywhere = 'role.assigned_everywhere';
    case RoleUnassignedEverywhere = 'role.unassigned_everywhere';

    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';

    case SupportSessionStarted = 'support_session.started';

    case DirectoryUserProvisioned = 'directory.user.provisioned';
    case DirectoryUserDeprovisioned = 'directory.user.deprovisioned';
    case DirectoryUserDeactivated = 'directory.user.deactivated';
    case DirectoryGroupMembershipChanged = 'directory.group.membership_changed';

    case DomainAdded = 'domain.added';
    case DomainRemoved = 'domain.removed';
    case DomainVerified = 'domain.verified';

    case ConnectionActivated = 'connection.activated';

    case EntitlementSet = 'entitlement.set';
    case EntitlementUpdated = 'entitlement.updated';
    case EntitlementRevoked = 'entitlement.revoked';

    case VaultGrantCreated = 'vault.grant.created';
    case VaultGrantRevoked = 'vault.grant.revoked';
    case VaultSecretRevoked = 'vault.secret.revoked';

    case GovernanceAccessRevoked = 'governance.access.revoked';

    /** Every event, present and future — for `on()`, not a real event name. */
    public const WILDCARD = '*';
}
