<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\App;
use Cbox\Id\Client\Management\Environment\Schemas\ManifestSync;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `apps.manifest.*` on the environment plane. */
class AppsManifest
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Set or clear the URL an app publishes its roles-and-permissions manifest at. Does not fetch it; apps.manifest.sync does.
     *
     * `PUT /apps/{id}/manifest` · action `apps.manifest.set` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{manifest_url?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function set(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.manifest.set'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Fetch an app's published manifest now and sync the roles and permissions it declares.
     *
     * `POST /apps/{id}/manifest/sync` · action `apps.manifest.sync` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<ManifestSync>|PendingApprovalResult<ApiResponse<ManifestSync>> : ApiResponse<ManifestSync>)
     */
    public function sync(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.manifest.sync'), [$id], [], $options, Value::dto(ManifestSync::fromArray(...)));
    }
}
