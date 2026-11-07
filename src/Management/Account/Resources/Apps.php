<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Account\Schemas\AppsListItem;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `apps.*` on the account plane. */
class Apps
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List the published apps visible to you in this environment. Optional organization context must be one you actively belong to.
     *
     * Requires scope `account:apps:read` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `GET /me/apps` · action `account.apps.list` · scope `account:apps:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<AppsListItem>>|PendingApprovalResult<ApiResponse<list<AppsListItem>>> : ApiResponse<list<AppsListItem>>)
     */
    public function list(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.apps.list'), [], $query, $options, Value::list(Value::dto(AppsListItem::fromArray(...))));
    }
}
