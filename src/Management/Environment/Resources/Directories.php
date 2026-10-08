<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Directory;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `directories.*` on the environment plane. */
class Directories
{
    public readonly DirectoriesGroups $groups;

    public readonly DirectoriesStatus $status;

    public readonly DirectoriesToken $token;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->groups = new DirectoriesGroups($transport);
        $this->status = new DirectoriesStatus($transport);
        $this->token = new DirectoriesToken($transport);
    }

    /**
     * Connect a Google Workspace or Microsoft Entra directory to sync an organization's people from, verifying the credentials first. Runs the first sync.
     *
     * `POST /directories/connect` · action `directories.connect` · scope `directory_sync:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id: string, provider: 'google_workspace'|'microsoft_entra', credentials: array{service_account_json?: string, admin_email?: string, tenant_id?: string, client_id?: string, client_secret?: string}}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function connect(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.connect'), [], $body, $options, Value::dto(Directory::fromArray(...)));
    }

    /**
     * Register a SCIM directory for an organization. Answers the SCIM base URL and a bearer token, shown once.
     *
     * `POST /directories` · action `directories.create` · scope `directory_sync:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id: string, name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.create'), [], $body, $options, Value::dto(Directory::fromArray(...)));
    }

    /**
     * Delete an inbound directory. Its token stops working; the people it provisioned keep their accounts.
     *
     * `DELETE /directories/{id}` · action `directories.delete` · scope `directory_sync:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Read one inbound directory: its provider, status, SCIM base URL and last sync error. Never its token or credentials.
     *
     * `GET /directories/{id}` · action `directories.get` · scope `directory_sync:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.get'), [$id], $query, $options, Value::dto(Directory::fromArray(...)));
    }

    /**
     * List the directories (SCIM, Google Workspace, Microsoft Entra) syncing people in, optionally for one organization, with their last sync error.
     *
     * `GET /directories` · action `directories.list` · scope `directory_sync:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Directory>|PendingApprovalResult<Page<Directory>> : Page<Directory>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('directories.list'), [], $query, $options, Value::dto(Directory::fromArray(...)));
    }

    /**
     * Every item of `directories.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, Directory, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('directories.list'), [], $query, $options, Value::dto(Directory::fromArray(...)));
    }

    /**
     * Rename an inbound directory.
     *
     * `PATCH /directories/{id}` · action `directories.update` · scope `directory_sync:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Directory>|PendingApprovalResult<ApiResponse<Directory>> : ApiResponse<Directory>)
     */
    public function update(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.update'), [$id], $body, $options, Value::dto(Directory::fromArray(...)));
    }
}
