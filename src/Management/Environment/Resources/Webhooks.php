<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Webhook;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `webhooks.*` on the environment plane. */
class Webhooks
{
    public readonly WebhooksSecret $secret;

    public readonly WebhooksSignatureScheme $signatureScheme;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->secret = new WebhooksSecret($transport);
        $this->signatureScheme = new WebhooksSignatureScheme($transport);
    }

    /**
     * Register a webhook endpoint for one organization or the whole environment. Returns its signing secret once.
     *
     * `POST /webhooks` · action `webhooks.create` · scope `webhooks:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{url: string, event_types: list<'user.created'|'user.updated'|'user.deactivated'|'user.login'|'user.reactivated'|'identity.linked'|'user.erased'|'organization.created'|'organization.suspended'|'organization.reactivated'|'organization.updated'|'organization.deleted'|'membership.created'|'membership.updated'|'membership.deleted'|'invitation.created'|'invitation.accepted'|'invitation.revoked'|'role.assigned'|'role.unassigned'|'role.assigned_everywhere'|'role.unassigned_everywhere'|'api_key.created'|'api_key.revoked'|'support_session.started'|'directory.user.provisioned'|'directory.user.deprovisioned'|'directory.user.deactivated'|'directory.group.membership_changed'|'domain.added'|'domain.removed'|'domain.verified'|'connection.activated'|'connection.certificate_expiring'|'entitlement.set'|'entitlement.updated'|'entitlement.revoked'|'vault.grant.created'|'vault.grant.revoked'|'vault.secret.revoked'|'governance.access.revoked'>, signature_scheme?: 'cbox'|'standard_webhooks', organization_id?: string|null, environment_wide?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.create'), [], $body, $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * Delete a webhook endpoint. It stops receiving events at once.
     *
     * `DELETE /webhooks/{id}` · action `webhooks.delete` · scope `webhooks:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get one webhook endpoint: URL, owner, subscribed events, health. Never its signing secret.
     *
     * `GET /webhooks/{id}` · action `webhooks.get` · scope `webhooks:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.get'), [$id], [], $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * List the webhook endpoints registered in this environment: URL, owner, subscribed events and whether each is active.
     *
     * `GET /webhooks` · action `webhooks.list` · scope `webhooks:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Webhook>|PendingApprovalResult<Page<Webhook>> : Page<Webhook>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('webhooks.list'), [], $query, $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * Every item of `webhooks.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Webhook, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('webhooks.list'), [], $query, $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * Pause a webhook endpoint: it stops receiving events until resumed.
     *
     * `POST /webhooks/{id}/pause` · action `webhooks.pause` · scope `webhooks:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function pause(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.pause'), [$id], [], $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * Resume a paused webhook endpoint: it receives events again.
     *
     * `POST /webhooks/{id}/resume` · action `webhooks.resume` · scope `webhooks:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function resume(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.resume'), [$id], [], $options, Value::dto(Webhook::fromArray(...)));
    }

    /**
     * Change a webhook endpoint's URL and/or the events it subscribes to.
     *
     * `PATCH /webhooks/{id}` · action `webhooks.update` · scope `webhooks:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{url?: string, event_types?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Webhook>|PendingApprovalResult<ApiResponse<Webhook>> : ApiResponse<Webhook>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('webhooks.update'), [$id], $body, $options, Value::dto(Webhook::fromArray(...)));
    }
}
