<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\ValueObjects\ServiceConnection;
use Cbox\Id\Client\ValueObjects\ServiceConnectionLease;

interface ServiceConnections
{
    /** The access token must come from an organization-bound administrator authorization. */
    public function connect(string $accessToken, string $organization, string $service, string $audience): ServiceConnection;

    public function status(ServiceConnection $connection): bool;

    public function lease(ServiceConnection $connection): ServiceConnectionLease;

    public function revoke(string $accessToken, ServiceConnection $connection): void;
}
