<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\Member;
use Cbox\Id\Client\Management\Workspace\Schemas\TeamInvitation;

/** `team.invitations.*` on the workspace plane. */
class TeamInvitations
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List pending invitations
     *
     * Requires scope `team:read` and the `read-members` capability. The team's invitations nobody has accepted yet, newest first (at most 100).
     *
     * `GET /workspace/invitations` · action `team.invitations.list` · scope `team:read`
     *
     * @return ApiResponse<list<TeamInvitation>>
     */
    public function list(?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('team.invitations.list'), [], [], $options, Value::list(Value::dto(TeamInvitation::fromArray(...))));
    }

    /**
     * Re-send an invitation
     *
     * Requires scope `team:write` and the `manage-members` capability. Mails a fresh link; the earlier link stops
     * working and the invitation gets a new `id`. At most once a minute per address
     * (`429`, `too_soon`). If the mail server refuses, the earlier invitation is kept (`503`).
     *
     * `POST /workspace/invitations/{id}/resend` · action `team.invitations.resend` · scope `team:write`
     *
     * @return ApiResponse<Member>
     */
    public function resend(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('team.invitations.resend'), [$id], [], $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Withdraw an invitation
     *
     * Requires scope `team:write` and the `manage-members` capability. The link stops working. An invitation that is not pending on this organization is a `404`.
     *
     * `DELETE /workspace/invitations/{id}` · action `team.invitations.revoke` · scope `team:write`
     *
     * @return ApiResponse<null>
     */
    public function revoke(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('team.invitations.revoke'), [$id], [], $options, Value::none(...));
    }
}
