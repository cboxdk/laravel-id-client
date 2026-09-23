<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Support\Discovery;

/**
 * A discovery document and JWKS served from memory, so the real verifiers run in a test
 * with no HTTP and no fake issuer: tokens from {@see TokenFactory} are signed with the key
 * published here, and verified by exactly the code that verifies production tokens.
 */
class FakeDiscovery extends Discovery
{
    /**
     * @param  array<string, mixed>  $fakeJwks
     */
    public function __construct(private readonly string $fakeIssuer, private readonly array $fakeJwks)
    {
        parent::__construct($fakeIssuer, 0, 1);
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        $base = rtrim($this->fakeIssuer, '/');

        return [
            'issuer' => $this->fakeIssuer,
            'authorization_endpoint' => $base.'/oauth/authorize',
            'token_endpoint' => $base.'/oauth/token',
            'userinfo_endpoint' => $base.'/oauth/userinfo',
            'introspection_endpoint' => $base.'/oauth/introspect',
            'revocation_endpoint' => $base.'/oauth/revoke',
            'end_session_endpoint' => $base.'/oauth/logout',
            'jwks_uri' => $base.'/.well-known/jwks.json',
        ];
    }

    /** @return array<string, mixed> */
    public function jwks(): array
    {
        return $this->fakeJwks;
    }

    /** @return array<string, mixed> */
    public function refreshJwks(): array
    {
        return $this->fakeJwks;
    }
}
