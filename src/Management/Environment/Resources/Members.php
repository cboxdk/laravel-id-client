<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Member;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `members.*` on the environment plane. */
class Members
{
    public readonly MembersRoles $roles;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->roles = new MembersRoles($transport);
    }

    /**
     * Add an existing user as a member
     *
     * Straight in — no invitation, no mail. Name the user by
     * `user_id` or by `email`; they must exist in this environment (`422 user_not_found`).
     * A suspended or archived organization takes no new members (`409 organization_inactive`).
     *
     * `roles` grants access roles at the same time — ids, or your app's manifest keys with
     * `client_id` — with the environment's authority (staff roles included). One the
     * organization cannot hold is `422 role_not_assignable`; a segregation-of-duties
     * conflict is `409 role_conflict`, and nothing is added.
     *
     * `role` is `admin` or `member` (default `member`). `owner` is never assignable:
     * ownership moves with `transfer-ownership`.
     *
     * **Idempotent for the same role**: repeating the request answers `200` with the
     * existing membership. A member on a *different* role is `409 already_member` — change
     * the role with `PATCH`.
     *
     * `POST /organizations/{organization_id}/members` · action `members.add` · scope `members:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{user_id?: string, email?: string, role?: 'admin'|'member', roles?: list<string>, client_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function add(string $organizationId, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.add'), [$organizationId], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * List an organization's members
     *
     * Every membership, whatever its status, oldest first.
     *
     * `GET /organizations/{organization_id}/members` · action `members.list` · scope `members:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Member>|PendingApprovalResult<Page<Member>> : Page<Member>)
     */
    public function list(string $organizationId, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('members.list'), [$organizationId], $query, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Every item of `members.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Member, mixed, void>
     */
    public function listAll(string $organizationId, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('members.list'), [$organizationId], $query, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Remove a member
     *
     * The member's roles in the organization go with the
     * membership. Removing the only owner is refused with `409 last_owner`.
     *
     * `DELETE /organizations/{organization_id}/members/{user_id}` · action `members.remove` · scope `members:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $organizationId, string $userId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.remove'), [$organizationId, $userId], [], $options, Value::none(...));
    }

    /**
     * Change a member's role
     *
     * `role` is `admin` or `member`. Demoting the only
     * owner is refused with `409 last_owner` — transfer ownership first.
     *
     * `PATCH /organizations/{organization_id}/members/{user_id}` · action `members.update` · scope `members:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{role: 'admin'|'member'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function update(string $organizationId, string $userId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.update'), [$organizationId, $userId], $body, $options, Value::dto(Member::fromArray(...)));
    }
}
