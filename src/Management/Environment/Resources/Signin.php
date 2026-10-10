<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `signin.*` on the environment plane. */
class Signin
{
    public readonly SigninPolicy $policy;

    public readonly SigninSelfServiceSignup $selfServiceSignup;

    public readonly SigninSms $sms;

    public readonly SigninSocial $social;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->policy = new SigninPolicy($transport);
        $this->selfServiceSignup = new SigninSelfServiceSignup($transport);
        $this->sms = new SigninSms($transport);
        $this->social = new SigninSocial($transport);
    }
}
