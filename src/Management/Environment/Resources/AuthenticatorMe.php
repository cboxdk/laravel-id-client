<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AuthenticatorMeListResult;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Value;

/** `authenticator.me.*` on the environment plane. */
class AuthenticatorMe
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Verified identity for the authenticator
     *
     * Requires the provisioned public authenticator client, devices.manage and a DPoP proof. Available before device registration; revoked keys are refused.
     *
     * `GET /authenticator/me`
     *
     * @return ApiResponse<AuthenticatorMeListResult>
     */
    public function list(?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('authenticator.me.list'), [], [], $options, Value::dto(AuthenticatorMeListResult::fromArray(...)));
    }
}
