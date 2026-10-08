<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AgentRequest;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `approvals.*` on the environment plane. */
class Approvals
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Deny a pending approval request (CIBA) for the person it was raised for: the agent gets access_denied and no token.
     *
     * `POST /agent-requests/{request_id}/deny` · action `approvals.deny` · scope `approvals:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function deny(string $requestId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('approvals.deny'), [$requestId], [], $options, Value::none(...));
    }

    /**
     * List the pending requests from agents to act as one of this environment's people (CIBA): which app, for whom, and what it asks.
     *
     * `GET /agent-requests` · action `approvals.list` · scope `approvals:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AgentRequest>|PendingApprovalResult<Page<AgentRequest>> : Page<AgentRequest>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('approvals.list'), [], $query, $options, Value::dto(AgentRequest::fromArray(...)));
    }

    /**
     * Every item of `approvals.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, AgentRequest, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('approvals.list'), [], $query, $options, Value::dto(AgentRequest::fromArray(...)));
    }
}
