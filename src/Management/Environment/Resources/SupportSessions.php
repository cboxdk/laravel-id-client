<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SupportSession;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `support_sessions.*` on the environment plane. */
class SupportSessions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * End a support session now: every token it issued is revoked.
     *
     * `DELETE /support-sessions/{id}` · action `support_sessions.end` · scope `support:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function end(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('support_sessions.end'), [$id], [], $options, Value::none(...));
    }

    /**
     * Start a support session
     *
     * A member of your staff (`actor_user_id`) signs in to
     * one of your apps (`client_id`) AS one of a customer's users (`user_id` in
     * `organization_id`), for a stated `reason`, for at most 60 minutes.
     *
     * The actor must hold your app's own `support:impersonate` permission through an
     * **environment-wide** grant (see `PUT /users/{id}/environment-roles/{role_id}`);
     * otherwise `403 not_permitted`. The app must be a first-party app the environment
     * owns that uses the authorization-code grant (`422 client_not_eligible`). The target
     * must be an active member (`422 target_not_member`) of an active organization
     * (`409 organization_inactive`).
     *
     * Send `redirect_uri` (registered for the app) and a PKCE S256 `code_challenge` to get
     * the first authorization `code` in the response. Your app redeems it at `/oauth/token`
     * with the verifier; every token carries `act: {"sub": actor_user_id}` and there is
     * never a refresh token. The session is audited on the customer's trail and the
     * environment's, and announced as the `support_session.started` webhook.
     *
     * `scopes` in the response are exactly what every token of the session carries: the
     * ones asked for (or, with none, the app's registration), never `offline_access`, and
     * audienced the way the token endpoint audiences them — once any of them belongs to a
     * registered API, the token is for that API and a scope no API registered (such as
     * `apps.manifest`) is left out. Scopes of two registered APIs cannot be audienced to
     * one token and are refused before the session starts (`422 invalid_target`).
     *
     * `POST /support-sessions` · action `support_sessions.start` · scope `support:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{user_id: string, organization_id: string, client_id: string, actor_user_id: string, reason: string, ttl_minutes?: int, scopes?: list<string>, redirect_uri?: string, code_challenge?: string, nonce?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SupportSession>|PendingApprovalResult<ApiResponse<SupportSession>> : ApiResponse<SupportSession>)
     */
    public function start(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('support_sessions.start'), [], $body, $options, Value::dto(SupportSession::fromArray(...)));
    }
}
