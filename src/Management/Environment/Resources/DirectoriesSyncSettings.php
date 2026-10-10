<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Directory;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `directories.sync_settings.*` on the environment plane. */
class DirectoriesSyncSettings
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Set how often a pull directory syncs, and for an HR system which of its fields pass through onto people.
     *
     * `PATCH /directories/{id}/sync-settings` · action `directories.sync_settings.update` · scope `directory_sync:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, sync_interval_minutes?: int|null, custom_attributes?: list<string>, field_map?: array{id?: string, email?: string, first_name?: string, last_name?: string, display_name?: string, active?: string, on_leave?: string, hire_date?: string, termination_date?: string, department_id?: string, department?: string, manager_id?: string, title?: string}}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.sync_settings.update'), [$id], $body, $options, Value::dto(Directory::fromArray(...)));
    }
}
