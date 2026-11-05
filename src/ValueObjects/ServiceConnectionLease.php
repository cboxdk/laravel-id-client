<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/** A short-lived provider credential; never serialize it into browser props or logs. */
readonly class ServiceConnectionLease
{
    public function __construct(
        #[\SensitiveParameter] public string $accessToken,
        public int $expiresAt,
        public ServiceConnection $connection,
    ) {}
}
