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

/** `directories.credentials.*` on the environment plane. */
class DirectoriesCredentials
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Replace a pull directory's provider credentials, verifying the new ones first. Write-only; never returned.
     *
     * `PUT /directories/{id}/credentials` · action `directories.credentials.replace` · scope `directory_sync:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, credentials: array{report_url?: string, username?: string, password?: string, client_id?: string, client_secret?: string, refresh_token?: string, subdomain?: string, api_key?: string, api_token?: string, service_user_id?: string, service_user_token?: string, service_account_json?: string, admin_email?: string, tenant_id?: string}}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function replace(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.credentials.replace'), [$id], $body, $options, Value::dto(Directory::fromArray(...)));
    }
}
