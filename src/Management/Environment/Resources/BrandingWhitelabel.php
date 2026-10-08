<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\WhitelabelBranding;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `branding.whitelabel.*` on the environment plane. */
class BrandingWhitelabel
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read the white-label branding (palette, app name, email sender, logo) of the environment default or one organization.
     *
     * `GET /branding/whitelabel` · action `branding.whitelabel.get` · scope `branding:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<WhitelabelBranding>|PendingApprovalResult<ApiResponse<WhitelabelBranding>> : ApiResponse<WhitelabelBranding>)
     */
    public function get(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('branding.whitelabel.get'), [], $query, $options, Value::dto(WhitelabelBranding::fromArray(...)));
    }

    /**
     * Save the white-label branding (palette, app name, email sender, welcome email) of the environment default or one organization.
     *
     * `PUT /branding/whitelabel` · action `branding.whitelabel.set` · scope `branding:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, palette?: array{primary?: string|null, accent?: string|null, ring?: string|null, foreground?: string|null, background?: string|null}, app_name?: string|null, email_from_name?: string|null, email_template?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<WhitelabelBranding>|PendingApprovalResult<ApiResponse<WhitelabelBranding>> : ApiResponse<WhitelabelBranding>)
     */
    public function set(array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('branding.whitelabel.set'), [], $body, $options, Value::dto(WhitelabelBranding::fromArray(...)));
    }
}
