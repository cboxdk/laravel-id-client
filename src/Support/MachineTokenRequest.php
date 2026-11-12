<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

/**
 * Which client-credentials token: the issuer and client it is for, its scopes and its
 * resource. What {@see MachineTokenCache} needs to keep one and to ask for it again.
 */
readonly class MachineTokenRequest
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $issuer = '',
        public string $clientId = '',
        public array $scopes = [],
        public ?string $resource = null,
    ) {}
}
