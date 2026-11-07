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
use Cbox\Id\Client\Management\Workspace\Schemas\TeamInvitationReceipt;
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
     * @param  array{environment_access_version: string, all_environments: bool, environment_ids?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function environmentAccess(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.environment_access'), [$id], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Invite a member
     *
     * Requires scope `team:write` and the `manage-members` capability (`members:manage` for human members, or current ownership). Invites the address onto the
     * ID product team with the selected app roles. Existing shared membership and other
     * apps are preserved. Owner is never invited; ownership is transferred separately. An
     * earlier pending invitation for the same address and this app is superseded. List, re-send and
     * withdraw it under `/workspace/invitations`.
     *
     * `POST /workspace/members` · action `team.invite` · scope `team:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{email: string, name?: string|null, roles: list<'admin'|'developer'|'billing'|'viewer'>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<TeamInvitationReceipt>|PendingApprovalResult<ApiResponse<TeamInvitationReceipt>> : ApiResponse<TeamInvitationReceipt>)
     */
    public function invite(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.invite'), [], $body, $options, Value::dto(TeamInvitationReceipt::fromArray(...)));
    }

    /**
     * List members
     *
     * Requires scope `team:read` and the `read-members` capability (`members:read` for human members, or current ownership). The roster contains personal information; a machine key also needs an explicit role allowing member reads.
     *
     * `GET /workspace/members` · action `team.list` · scope `team:read` · danger: read
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
     * Remove native ID app roles while preserving shared organization membership and other apps. The current owner cannot be removed.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `DELETE /workspace/members/{id}` · action `team.remove` · scope `team:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{roles_version: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.remove'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Replace native ID app roles atomically. Shared organization membership is preserved.
     *
     * Requires scope `team:write` and a role that may `manage-members`.
     *
     * `PATCH /workspace/members/{id}/role` · action `team.role` · scope `team:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{roles: list<'viewer'|'developer'|'billing'|'admin'>, roles_version: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function role(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('team.role'), [$id], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Transfer shared organization ownership. Existing app roles are preserved; the previous owner may lose native ID access. Only the current owner, signed in, may do this.
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
