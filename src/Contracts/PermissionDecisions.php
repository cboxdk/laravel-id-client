<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\Authz\PermissionDecision;
use SensitiveParameter;

interface PermissionDecisions
{
    /**
     * Live app-scoped RBAC through Cbox ID; no local role resolution or decision cache.
     * Use an issuer-audience user token, or a client token with decisions:read.
     * Resource-audience tokens are intentionally not accepted by this endpoint.
     *
     * @param  list<string>  $permissions
     */
    public function check(#[SensitiveParameter] string $accessToken, string $subject, ?string $organization, array $permissions): PermissionDecision;
}
