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

/** `directories.token.*` on the environment plane. */
class DirectoriesToken
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Issue a new bearer token for a SCIM directory, returned once. The old token stops working immediately.
     *
     * `POST /directories/{id}/rotate` · action `directories.token.rotate` · scope `directory_sync:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function rotate(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.token.rotate'), [$id], $body, $options, Value::dto(Directory::fromArray(...)));
    }
}
