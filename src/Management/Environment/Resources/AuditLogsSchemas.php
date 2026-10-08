<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogSchema;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `audit_logs.schemas.*` on the environment plane. */
class AuditLogsSchemas
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Define the schema one audit-log action's events must match — allowed target types and metadata schemas (a subset of JSON Schema). Events of that action are then validated on arrival.
     *
     * `POST /audit-logs/schemas` · action `audit_logs.schemas.create` · scope `audit_logs:manage` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{action: string, targets?: list<array{type: string, metadata?: array<string, mixed>}>|null, actor_metadata?: array<string, mixed>, metadata?: array<string, mixed>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogSchema>|PendingApprovalResult<ApiResponse<AuditLogSchema>> : ApiResponse<AuditLogSchema>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.schemas.create'), [], $body, $options, Value::dto(AuditLogSchema::fromArray(...)));
    }

    /**
     * Delete an audit-log action's schema. Its events are then accepted unchecked — or refused, if the environment is in strict mode.
     *
     * `DELETE /audit-logs/schemas/{action}` · action `audit_logs.schemas.delete` · scope `audit_logs:manage` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $action, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.schemas.delete'), [$action], [], $options, Value::none(...));
    }

    /**
     * Read the schema one audit-log action's events are validated against.
     *
     * `GET /audit-logs/schemas/{action}` · action `audit_logs.schemas.get` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogSchema>|PendingApprovalResult<ApiResponse<AuditLogSchema>> : ApiResponse<AuditLogSchema>)
     */
    public function get(string $action, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.schemas.get'), [$action], [], $options, Value::dto(AuditLogSchema::fromArray(...)));
    }

    /**
     * List the audit-log schemas this environment validates events against, one per action.
     *
     * `GET /audit-logs/schemas` · action `audit_logs.schemas.list` · scope `audit_logs:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AuditLogSchema>|PendingApprovalResult<Page<AuditLogSchema>> : Page<AuditLogSchema>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('audit_logs.schemas.list'), [], $query, $options, Value::dto(AuditLogSchema::fromArray(...)));
    }

    /**
     * Every item of `audit_logs.schemas.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, AuditLogSchema, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('audit_logs.schemas.list'), [], $query, $options, Value::dto(AuditLogSchema::fromArray(...)));
    }

    /**
     * Replace an audit-log action's schema with a new version (omitted parts are removed). Recorded events keep the version they were checked against.
     *
     * `PUT /audit-logs/schemas/{action}` · action `audit_logs.schemas.update` · scope `audit_logs:manage` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{targets?: list<array{type: string, metadata?: array<string, mixed>}>|null, actor_metadata?: array<string, mixed>, metadata?: array<string, mixed>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AuditLogSchema>|PendingApprovalResult<ApiResponse<AuditLogSchema>> : ApiResponse<AuditLogSchema>)
     */
    public function update(string $action, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('audit_logs.schemas.update'), [$action], $body, $options, Value::dto(AuditLogSchema::fromArray(...)));
    }
}
