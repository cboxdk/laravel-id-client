<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RadarSettings;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `radar.mode.*` on the environment plane. */
class RadarMode
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Switch this environment's Radar between `monitor` (verdicts recorded, never acted on) and `enforce` (blocks refuse sign-ins and sign-ups, challenges demand a second factor). Changes the environment's security posture.
     *
     * `PUT /radar/mode` · action `radar.mode.set` · scope `radar:manage` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{mode: 'monitor'|'enforce'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<RadarSettings>|PendingApprovalResult<ApiResponse<RadarSettings>> : ApiResponse<RadarSettings>)
     */
    public function set(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('radar.mode.set'), [], $body, $options, Value::dto(RadarSettings::fromArray(...)));
    }
}
