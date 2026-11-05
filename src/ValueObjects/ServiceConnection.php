<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/** An organization-owned grant. It contains no credential. */
readonly class ServiceConnection
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $service,
        public string $consumerClientId,
        public string $audience,
        public array $scopes,
    ) {}
}
