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

/** `directories.status.*` on the environment plane. */
class DirectoriesStatus
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Pause or resume an inbound directory's provisioning.
     *
     * `POST /directories/{id}/status` · action `directories.status.set` · scope `directory_sync:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{active: bool, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function set(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.status.set'), [$id], $body, $options, Value::dto(Directory::fromArray(...)));
    }
}
