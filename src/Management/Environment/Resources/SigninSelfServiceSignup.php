<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SelfServiceSignup;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `signin.self_service_signup.*` on the environment plane. */
class SigninSelfServiceSignup
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Switch self-service sign-up on or off: whether people can create an account and their own organization in this environment.
     *
     * `PUT /sign-in/self-service-signup` · action `signin.self_service_signup.set` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{enabled: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SelfServiceSignup>|PendingApprovalResult<ApiResponse<SelfServiceSignup>> : ApiResponse<SelfServiceSignup>)
     */
    public function set(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.self_service_signup.set'), [], $body, $options, Value::dto(SelfServiceSignup::fromArray(...)));
    }
}
