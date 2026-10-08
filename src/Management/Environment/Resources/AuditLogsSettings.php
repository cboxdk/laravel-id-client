<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogSettings;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `audit_logs.settings.*` on the environment plane. */
class AuditLogsSettings
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read this environment's audit-log settings: retention in days and whether strict schemas are on.
     *
     * `GET /audit-logs/settings` · action `audit_logs.settings.get` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogSettings>|PendingApprovalResult<ApiResponse<AuditLogSettings>> : ApiResponse<AuditLogSettings>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.settings.get'), [], [], $options, Value::dto(AuditLogSettings::fromArray(...)));
    }

    /**
     * Change this environment's audit-log retention (days, 1–3650) and strict mode. Shortening retention deletes older events at the next daily prune — irreversibly.
     *
     * `PATCH /audit-logs/settings` · action `audit_logs.settings.update` · scope `audit_logs:manage` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{retention_days?: int, strict_schemas?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogSettings>|PendingApprovalResult<ApiResponse<AuditLogSettings>> : ApiResponse<AuditLogSettings>)
     */
    public function update(array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.settings.update'), [], $body, $options, Value::dto(AuditLogSettings::fromArray(...)));
    }
}
