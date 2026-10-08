<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuditEntry;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `audit.*` on the environment plane. */
class Audit
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read this environment's audit trail oldest first, optionally narrowed by action, actor type or organization; pass the last id as `after` to page.
     *
     * `GET /audit-log` · action `audit.list` · scope `audit:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, action?: string, actor_type?: 'user'|'service'|'system'|'operator'|'organization_member', organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<AuditEntry>|PendingApprovalResult<Page<AuditEntry>> : Page<AuditEntry>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('audit.list'), [], $query, $options, Value::dto(AuditEntry::fromArray(...)));
    }

    /**
     * Every item of `audit.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, action?: string, actor_type?: 'user'|'service'|'system'|'operator'|'organization_member', organization_id?: string}  $query
     * @return Generator<int, AuditEntry, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('audit.list'), [], $query, $options, Value::dto(AuditEntry::fromArray(...)));
    }
}
