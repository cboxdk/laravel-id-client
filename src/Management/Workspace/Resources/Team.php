<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\Member;
use Generator;

/** `team.*` on the workspace plane. */
class Team
{
    public readonly TeamInvitations $invitations;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->invitations = new TeamInvitations($transport);
    }

    /**
     * Set which of the workspace's environments a team member reaches: all of them, or the listed ones.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `PUT /workspace/members/{id}/access` · action `team.environment_access` · scope `team:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{all_environments: bool, environment_ids?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function environmentAccess(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.environment_access'), [$id], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Invite a member
     *
     * Requires scope `team:write` and the `manage-members` capability (owner/admin). Invites the address onto the
     * workspace's team — the same invitation the console's Team page sends: a mail naming
     * this key as the inviter and the role, with a signed link on which the invitee sets a
     * password and is signed in. Owner is never invited; ownership is transferred. An
     * earlier pending invitation for the same address is superseded. List, re-send and
     * withdraw it under `/workspace/invitations`.
     *
     * `POST /workspace/members` · action `team.invite` · scope `team:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{email: string, name?: string|null, role: 'admin'|'developer'|'member'|'viewer'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function invite(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.invite'), [], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * List members
     *
     * Requires scope `team:read` and the `read-members` capability (owner/admin/viewer). The roster is PII — a developer key is refused.
     *
     * `GET /workspace/members` · action `team.list` · scope `team:read`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, page?: int}  $query
     * @return ($options is ReturnPendingApproval ? Page<Member>|PendingApprovalResult<Page<Member>> : Page<Member>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('team.list'), [], $query, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Every item of `team.list`, fetching pages lazily as the iteration reaches them — `page` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Member, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('team.list'), [], $query, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Remove a member from the workspace's team. The owner is never removed; transfer ownership first.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `DELETE /workspace/members/{id}` · action `team.remove` · scope `team:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.remove'), [$id], [], $options, Value::none(...));
    }

    /**
     * Change a team member's role (admin, developer, member or viewer). The owner's role changes only by transferring ownership.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `PATCH /workspace/members/{id}/role` · action `team.role` · scope `team:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{role: 'admin'|'developer'|'member'|'viewer'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function role(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.role'), [$id], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Make another member the workspace's owner; the current owner stays on as an admin. Only the owner, signed in, may do this.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `POST /workspace/members/{id}/transfer-ownership` · action `team.transfer_ownership` · scope `team:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function transferOwnership(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.transfer_ownership'), [$id], [], $options, Value::none(...));
    }
}
