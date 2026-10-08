<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Invitation;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `invitations.*` on the environment plane. */
class Invitations
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List an organization's pending invitations
     *
     * Pending and unexpired only.
     *
     * `GET /organizations/{organization_id}/invitations` · action `invitations.list` · scope `invitations:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Invitation>|PendingApprovalResult<Page<Invitation>> : Page<Invitation>)
     */
    public function list(string $organizationId, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('invitations.list'), [$organizationId], $query, $options, Value::dto(Invitation::fromArray(...)));
    }

    /**
     * Every item of `invitations.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Invitation, mixed, void>
     */
    public function listAll(string $organizationId, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('invitations.list'), [$organizationId], $query, $options, Value::dto(Invitation::fromArray(...)));
    }

    /**
     * Mail a pending invitation again
     *
     * On a **fresh link**: only a hash of the old one is
     * kept, so it cannot be re-sent, and the reason people ask is usually that it expired.
     * The response is the NEW invitation — its `id` replaces the old one, which stops
     * working. Its roles and app context move with it.
     *
     * One re-send per address per minute (`429 too_soon`); `409 not_pending` for one that
     * was accepted, withdrawn or has expired; `503 mail_failed` keeps the invitation.
     *
     * `POST /organizations/{organization_id}/invitations/{invitation_id}/resend` · action `invitations.resend` · scope `invitations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Invitation>|PendingApprovalResult<ApiResponse<Invitation>> : ApiResponse<Invitation>)
     */
    public function resend(string $organizationId, string $invitationId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('invitations.resend'), [$organizationId, $invitationId], [], $options, Value::dto(Invitation::fromArray(...)));
    }

    /**
     * Withdraw a pending invitation
     *
     * The link stops working, and the roles parked
     * for it go with it. `409 not_pending` for one already accepted, withdrawn or expired.
     *
     * `DELETE /organizations/{organization_id}/invitations/{invitation_id}` · action `invitations.revoke` · scope `invitations:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $organizationId, string $invitationId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('invitations.revoke'), [$organizationId, $invitationId], [], $options, Value::none(...));
    }

    /**
     * Invite someone to the organization
     *
     * Mails a link; the person joins when they open it
     * and confirm. Inviting an address again replaces its earlier pending invitation.
     *
     * - `role` — `admin` or `member` (default `member`). Never `owner`.
     * - `roles` — access roles granted when the invitation is accepted: role ids, or your
     *   app's manifest **keys** when `client_id` names the app that declared them. Only
     *   roles the organization's own administrators could grant are accepted: a **staff
     *   role** is refused (`422 role_not_assignable`) — grant it after they join, with
     *   `PUT …/members/{user_id}/roles/{role_id}`.
     * - `client_id` + `return_to` — after accepting, the person is sent to `return_to`,
     *   which must be on one of that app's registered redirect-URI origins (checked again
     *   when the invitation is accepted).
     * - `inviter_name` — who the mail says it is from. Defaults to the app's name, or the
     *   environment's.
     *
     * Refusals: `409 already_member`, `409 access_role_conflict` (segregation of duties),
     * `422 unknown_role`, `422 role_not_assignable`, `422 unknown_app`,
     * `422 return_without_app`, `422 return_to_malformed`, `422 return_to_not_registered`,
     * `503 mail_failed` (nothing was created — retry).
     *
     * `POST /organizations/{organization_id}/invitations` · action `invitations.send` · scope `invitations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{email: string, role?: 'admin'|'member', roles?: list<string>, client_id?: string|null, return_to?: string|null, inviter_name?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Invitation>|PendingApprovalResult<ApiResponse<Invitation>> : ApiResponse<Invitation>)
     */
    public function send(string $organizationId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('invitations.send'), [$organizationId], $body, $options, Value::dto(Invitation::fromArray(...)));
    }
}
