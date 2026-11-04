<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

final readonly class ResourceAuthorizationResult
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string,string>  $context
     */
    public function __construct(
        #[\SensitiveParameter] private string $bearer,
        public string $subject,
        public string $organizationId,
        public string $resource,
        public array $scopes,
        public int $expiresAt,
        public array $context,
    ) {}

    public function accessToken(): string
    {
        return $this->bearer;
    }

    /** @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return ['subject' => $this->subject, 'organizationId' => $this->organizationId, 'resource' => $this->resource, 'expiresAt' => $this->expiresAt];
    }
}
