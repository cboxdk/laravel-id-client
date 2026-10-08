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

/** `webhooks.signature_scheme.*` on the environment plane. */
class WebhooksSignatureScheme
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Change how a webhook endpoint's deliveries are signed (cbox or standard_webhooks). No new secret is issued: a hex secret is used as whsec_ + base64 of itself. Update the receiver first.
     *
     * `POST /webhooks/{id}/signature-scheme` · action `webhooks.signature_scheme.change` · scope `webhooks:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{signature_scheme: 'cbox'|'standard_webhooks'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function change(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.signature_scheme.change'), [$id], $body, $options, Value::dto(Webhook::fromArray(...)));
    }
}
