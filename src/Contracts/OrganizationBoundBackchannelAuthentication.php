<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\ValueObjects\PendingBackchannelAuthentication;

/** Optional Cbox extension; existing BackchannelAuthentication implementations are unchanged. */
interface OrganizationBoundBackchannelAuthentication extends BackchannelAuthentication
{
    public function startForOrganization(
        string $organizationId,
        string $loginHint,
        string $scope,
        string $bindingMessage,
        ?string $resource = null,
        ?int $requestedExpiry = null,
        ?string $nonce = null,
        ?string $clientNotificationToken = null,
    ): PendingBackchannelAuthentication;
}
