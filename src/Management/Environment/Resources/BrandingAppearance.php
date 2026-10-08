<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Appearance;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `branding.appearance.*` on the environment plane. */
class BrandingAppearance
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read the hosted sign-in theme: the environment default, or one organization's own.
     *
     * `GET /branding/appearance` · action `branding.appearance.get` · scope `branding:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<Appearance>|PendingApprovalResult<ApiResponse<Appearance>> : ApiResponse<Appearance>)
     */
    public function get(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('branding.appearance.get'), [], $query, $options, Value::dto(Appearance::fromArray(...)));
    }

    /**
     * Set the hosted sign-in theme (preset, colours, corners, type, logo) for the environment default or one organization.
     *
     * `PUT /branding/appearance` · action `branding.appearance.set` · scope `branding:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, theme: array{preset?: string, radius?: string, font?: string, light?: array{primary?: string, background?: string, foreground?: string, muted?: string}, dark?: array{primary?: string, background?: string, foreground?: string, muted?: string}}, logo?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Appearance>|PendingApprovalResult<ApiResponse<Appearance>> : ApiResponse<Appearance>)
     */
    public function set(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('branding.appearance.set'), [], $body, $options, Value::dto(Appearance::fromArray(...)));
    }
}
