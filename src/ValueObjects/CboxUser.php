<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * The authenticated Cbox ID user returned by a completed login. `id` is the stable
 * opaque subject (`sub`) you key your local account on. `claims` is the full,
 * verified id_token + userinfo claim set; the named accessors are conveniences over
 * it. The tokens let you call Cbox ID APIs on the user's behalf.
 */
readonly class CboxUser
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
        public ?string $organizationId,
        public array $claims,
        public string $accessToken,
        public ?string $refreshToken,
        public ?string $idToken,
        public int $expiresIn,
    ) {}

    public function claim(string $key): mixed
    {
        return $this->claims[$key] ?? null;
    }
}
