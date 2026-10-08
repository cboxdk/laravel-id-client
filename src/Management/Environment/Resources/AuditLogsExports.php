<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogExport;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `audit_logs.exports.*` on the environment plane. */
class AuditLogsExports
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Start a CSV export of audit events (same filters as the list). It is written on the queue: poll audit_logs.exports.get until state is ready, then download from its short-lived url.
     *
     * `POST /audit-logs/exports` · action `audit_logs.exports.create` · scope `audit_logs:export` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string, actions?: list<string>, actor_id?: string, target_id?: string, range_start?: string, range_end?: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogExport>|PendingApprovalResult<ApiResponse<AuditLogExport>> : ApiResponse<AuditLogExport>)
     */
    public function create(array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.exports.create'), [], $body, $options, Value::dto(AuditLogExport::fromArray(...)));
    }

    /**
     * Read an audit-log export's state; once ready it carries a signed download url valid for a few minutes (read it again for a fresh one).
     *
     * `GET /audit-logs/exports/{id}` · action `audit_logs.exports.get` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogExport>|PendingApprovalResult<ApiResponse<AuditLogExport>> : ApiResponse<AuditLogExport>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.exports.get'), [$id], [], $options, Value::dto(AuditLogExport::fromArray(...)));
    }
}
