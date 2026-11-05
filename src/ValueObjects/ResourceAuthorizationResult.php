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
        private ?DpopKey $holder = null,
    ) {}

    public function accessToken(): string
    {
        return $this->bearer;
    }

    /** @return array<string,string> */
    public function requestHeaders(string $method, string $url): array
    {
        $endpoint = parse_url($url);
        $resource = parse_url($this->resource);
        if (! is_array($endpoint) || ! is_array($resource) || ($endpoint['scheme'] ?? '') !== 'https' || isset($endpoint['user']) || isset($endpoint['fragment'])
            || ! isset($endpoint['host'], $resource['host']) || strcasecmp($endpoint['host'], $resource['host']) !== 0
            || ($endpoint['port'] ?? 443) !== ($resource['port'] ?? 443)) {
            throw new \InvalidArgumentException('The endpoint must belong to the authorized resource origin.');
        }
        if ($this->holder === null) {
            return ['Authorization' => 'Bearer '.$this->bearer];
        }

        return ['Authorization' => 'DPoP '.$this->bearer, 'DPoP' => $this->holder->proof($method, $url, $this->bearer)];
    }

    /** @return array<string,mixed> */
    public function __debugInfo(): array
    {
        return ['subject' => $this->subject, 'organizationId' => $this->organizationId, 'resource' => $this->resource, 'expiresAt' => $this->expiresAt];
    }
}
