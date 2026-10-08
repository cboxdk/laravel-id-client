<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\LegacyLogin as LegacyLoginSchema;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `legacy_login.*` on the environment plane. */
class LegacyLogin
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Approve the declared legacy login endpoint: sign-ins for people not yet migrated, with their passwords, go to that URL.
     *
     * `POST /legacy-login/approve` · action `legacy_login.approve` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<LegacyLoginSchema>|PendingApprovalResult<ApiResponse<LegacyLoginSchema>> : ApiResponse<LegacyLoginSchema>)
     */
    public function approve(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('legacy_login.approve'), [], [], $options, Value::dto(LegacyLoginSchema::fromArray(...)));
    }

    /**
     * Read the legacy login endpoint an app declared for migration, and whether it is approved to receive sign-ins.
     *
     * `GET /legacy-login` · action `legacy_login.get` · scope `signin:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<LegacyLoginSchema>|PendingApprovalResult<ApiResponse<LegacyLoginSchema>> : ApiResponse<LegacyLoginSchema>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('legacy_login.get'), [], [], $options, Value::dto(LegacyLoginSchema::fromArray(...)));
    }

    /**
     * Ask the declared legacy login endpoint whether it knows an address — your own — to test it before approving. Sends no password.
     *
     * `POST /legacy-login/probe` · action `legacy_login.probe` · scope `signin:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{email: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function probe(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('legacy_login.probe'), [], $body, $options, Value::object(...));
    }

    /**
     * Withdraw the legacy login approval. People not yet migrated can no longer sign in; the declaration stays on file.
     *
     * `POST /legacy-login/revoke` · action `legacy_login.revoke` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<LegacyLoginSchema>|PendingApprovalResult<ApiResponse<LegacyLoginSchema>> : ApiResponse<LegacyLoginSchema>)
     */
    public function revoke(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('legacy_login.revoke'), [], [], $options, Value::dto(LegacyLoginSchema::fromArray(...)));
    }
}
