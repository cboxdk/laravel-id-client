<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

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

    /** Short-lived server-session representation; do not return this string to a browser. */
    public function seal(): string
    {
        return Crypt::encryptString(json_encode(['token' => $this->bearer, 'subject' => $this->subject, 'organization' => $this->organizationId, 'resource' => $this->resource,
            'scopes' => $this->scopes, 'expires' => $this->expiresAt, 'context' => $this->context, 'holder' => $this->holder?->seal()], JSON_THROW_ON_ERROR));
    }

    public static function open(#[\SensitiveParameter] string $sealed): ?self
    {
        try {
            $data = json_decode(Crypt::decryptString($sealed), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }
        if (! is_array($data) || ! is_string($data['token'] ?? null) || ! is_string($data['subject'] ?? null)
            || ! is_string($data['organization'] ?? null) || ! is_string($data['resource'] ?? null)
            || ! is_int($data['expires'] ?? null) || $data['expires'] <= time() || ! is_array($data['scopes'] ?? null) || ! array_is_list($data['scopes'])
            || ! is_array($data['context'] ?? null) || ! array_key_exists('holder', $data)) {
            return null;
        }
        $scopes = [];
        $context = [];
        foreach ($data['scopes'] as $scope) {
            if (! is_string($scope)) {
                return null;
            }
            $scopes[] = $scope;
        }
        foreach ($data['context'] as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                return null;
            }
            $context[$key] = $value;
        }
        $key = is_string($data['holder']) ? DpopKey::open($data['holder']) : null;
        if ($data['holder'] !== null && $key === null) {
            return null;
        }

        return new self($data['token'], $data['subject'], $data['organization'], $data['resource'], $scopes, $data['expires'], $context, $key);
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
