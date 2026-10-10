<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Pipe;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `pipes.grants.*` on the environment plane. */
class PipesGrants
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Grant an app (by OAuth client id) the right to lease fresh access tokens for the accounts people connected through this pipe.
     *
     * `POST /pipes/{id}/grants` · action `pipes.grants.create` · scope `pipes:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Pipe>|PendingApprovalResult<ApiResponse<Pipe>> : ApiResponse<Pipe>)
     */
    public function create(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.grants.create'), [$id], $body, $options, Value::dto(Pipe::fromArray(...)));
    }

    /**
     * Withdraw an app's right to lease the tokens people connected through this pipe.
     *
     * `DELETE /pipes/{id}/grants/{client_id}` · action `pipes.grants.delete` · scope `pipes:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, string $clientId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.grants.delete'), [$id, $clientId], [], $options, Value::none(...));
    }
}
