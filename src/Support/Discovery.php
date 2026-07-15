<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves the Cbox ID instance's OIDC endpoints from its discovery document
 * (`{issuer}/.well-known/openid-configuration`) and its JWKS, both cached. This is
 * what lets you configure only the issuer URL — every endpoint is discovered.
 */
final class Discovery
{
    public function __construct(
        private readonly string $issuer,
        private readonly int $cacheTtl,
        private readonly int $timeout,
    ) {}

    public function endpoint(string $key): string
    {
        $document = $this->document();
        $value = $document[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw ClientConfigurationException::because("The Cbox ID discovery document is missing '{$key}'.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        /** @var array<string, mixed> $doc */
        $doc = Cache::remember('cbox-id-client:discovery:'.md5($this->issuer), $this->cacheTtl, function (): array {
            $response = Http::timeout($this->timeout)->get(rtrim($this->issuer, '/').'/.well-known/openid-configuration');

            if (! $response->successful()) {
                throw ClientConfigurationException::because('Could not load the Cbox ID discovery document from '.$this->issuer.'.');
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
        });

        return $doc;
    }

    /**
     * @return array<string, mixed>
     */
    public function jwks(): array
    {
        $uri = $this->endpoint('jwks_uri');

        /** @var array<string, mixed> $jwks */
        $jwks = Cache::remember('cbox-id-client:jwks:'.md5($uri), $this->cacheTtl, function () use ($uri): array {
            $response = Http::timeout($this->timeout)->get($uri);

            if (! $response->successful()) {
                throw ClientConfigurationException::because('Could not load the Cbox ID signing keys (JWKS).');
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
        });

        return $jwks;
    }
}
