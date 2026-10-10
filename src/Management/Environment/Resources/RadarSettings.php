<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RadarSettings as RadarSettingsSchema;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `radar.settings.*` on the environment plane. */
class RadarSettings
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read this environment's Radar settings: the mode (monitor or enforce, and whether it is inherited from the deployment), the IP intelligence source, and every built-in rule with its action and threshold.
     *
     * `GET /radar/settings` · action `radar.settings.get` · scope `radar:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarSettingsSchema>|PendingApprovalResult<ApiResponse<RadarSettingsSchema>> : ApiResponse<RadarSettingsSchema>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.settings.get'), [], [], $options, Value::dto(RadarSettingsSchema::fromArray(...)));
    }

    /**
     * Tune this environment's built-in Radar rules: `builtin_rules` maps a rule key (credential_stuffing, bot_velocity, account_attack, impossible_travel, new_device, anonymous_network, hosting_network, disposable_email, risk_score_reject, risk_score_elevated) to any of enabled, action and threshold.
     *
     * `PATCH /radar/settings` · action `radar.settings.update` · scope `radar:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{builtin_rules: array<string, mixed>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarSettingsSchema>|PendingApprovalResult<ApiResponse<RadarSettingsSchema>> : ApiResponse<RadarSettingsSchema>)
     */
    public function update(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.settings.update'), [], $body, $options, Value::dto(RadarSettingsSchema::fromArray(...)));
    }
}
