<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Webhook;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `webhooks.secret.*` on the environment plane. */
class WebhooksSecret
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Issue a new signing secret for a webhook endpoint, returned once. The old secret stops verifying immediately.
     *
     * `POST /webhooks/{id}/rotate` · action `webhooks.secret.rotate` · scope `webhooks:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function rotate(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.secret.rotate'), [$id], [], $options, Value::dto(Webhook::fromArray(...)));
    }
}
