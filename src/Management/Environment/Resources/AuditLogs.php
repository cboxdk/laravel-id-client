<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogVerification;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `audit_logs.*` on the environment plane. */
class AuditLogs
{
    public readonly AuditLogsEvents $events;

    public readonly AuditLogsExports $exports;

    public readonly AuditLogsSchemas $schemas;

    public readonly AuditLogsSettings $settings;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->events = new AuditLogsEvents($transport);
        $this->exports = new AuditLogsExports($transport);
        $this->schemas = new AuditLogsSchemas($transport);
        $this->settings = new AuditLogsSettings($transport);
    }

    /**
     * Re-hash one organization's audit-event chain (from the oldest event retention kept, or from_sequence) and report whether every event is unchanged and in place.
     *
     * `GET /audit-logs/verify` · action `audit_logs.verify` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string, from_sequence?: int, limit?: int}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogVerification>|PendingApprovalResult<ApiResponse<AuditLogVerification>> : ApiResponse<AuditLogVerification>)
     */
    public function verify(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.verify'), [], $query, $options, Value::dto(AuditLogVerification::fromArray(...)));
    }
}
