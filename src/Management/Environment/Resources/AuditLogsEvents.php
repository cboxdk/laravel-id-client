<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogEvent;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogEventBatch;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `audit_logs.events.*` on the environment plane. */
class AuditLogsEvents
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Record 1–100 audit events your app's users caused, each for one of your organizations (customers); checked against the action's schema when it has one and appended to that organization's tamper-evident chain. Send an Idempotency-Key.
     *
     * `POST /audit-logs/events` · action `audit_logs.events.create` · scope `audit_logs:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{events: list<array{organization_id: string, action: string, occurred_at: string, actor: array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}, targets?: list<array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}>, context?: array{location?: string|null, user_agent?: string|null}, metadata?: array<string, mixed>}>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogEventBatch>|PendingApprovalResult<ApiResponse<AuditLogEventBatch>> : ApiResponse<AuditLogEventBatch>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.events.create'), [], $body, $options, Value::dto(AuditLogEventBatch::fromArray(...)));
    }

    /**
     * Read audit events newest first, for one organization or the whole environment, filtered by action, actor, target and time range; pass `next_cursor` as `after` to page.
     *
     * `GET /audit-logs/events` · action `audit_logs.events.list` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string, actions?: list<string>, actor_id?: string, target_id?: string, range_start?: string, range_end?: string, order?: 'desc'|'asc', limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AuditLogEvent>|PendingApprovalResult<Page<AuditLogEvent>> : Page<AuditLogEvent>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('audit_logs.events.list'), [], $query, $options, Value::dto(AuditLogEvent::fromArray(...)));
    }

    /**
     * Every item of `audit_logs.events.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string, actions?: list<string>, actor_id?: string, target_id?: string, range_start?: string, range_end?: string, order?: 'desc'|'asc', limit?: int}  $query
     * @return Generator<int, AuditLogEvent, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('audit_logs.events.list'), [], $query, $options, Value::dto(AuditLogEvent::fromArray(...)));
    }
}
