<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AppCatalogListItem;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `app_catalog.*` on the environment plane. */
class AppCatalog
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Remove a catalog app without deleting its OAuth clients or user data.
     *
     * `DELETE /app-catalog/{key}` · action `app_catalog.delete` · scope `app_catalog:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{version: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $key, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('app_catalog.delete'), [$key], $body, $options, Value::none(...));
    }

    /**
     * List catalog apps, including drafts and visibility settings.
     *
     * `GET /app-catalog` · action `app_catalog.list` · scope `app_catalog:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<AppCatalogListItem>>|PendingApprovalResult<ApiResponse<list<AppCatalogListItem>>> : ApiResponse<list<AppCatalogListItem>>)
     */
    public function list(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('app_catalog.list'), [], [], $options, Value::list(Value::dto(AppCatalogListItem::fromArray(...))));
    }

    /**
     * Create or replace a catalog app, its icon and visibility. Version zero creates; a stale version conflicts.
     *
     * `PUT /app-catalog/{key}` · action `app_catalog.save` · scope `app_catalog:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{version: int, name: string, description?: string|null, launch_url: string, enabled: bool, position: int, audience: 'everyone'|'organizations'|'users', organization_ids?: list<string>, user_ids?: list<string>, client_ids?: list<string>, required_feature?: string|null, icon?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function save(string $key, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('app_catalog.save'), [$key], $body, $options, Value::object(...));
    }

    /**
     * Read a catalog app and its publication settings.
     *
     * `GET /app-catalog/{key}` · action `app_catalog.show` · scope `app_catalog:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function show(string $key, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('app_catalog.show'), [$key], [], $options, Value::object(...));
    }
}
