<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Concerns\ReadsAuthorizationClaims;
use Cbox\Id\Client\Contracts\Principal;

/**
 * The authenticated Cbox ID user returned by a completed login. `id` is the stable
 * opaque subject (`sub`) you key your local account on. `claims` is the full,
 * verified id_token + userinfo claim set; the named accessors are conveniences over
 * it. The tokens let you call Cbox ID APIs on the user's behalf.
 *
 * Tenancy and authorization read straight off the claims: `organization()` (id, name and
 * the `org_role` tier), `roles()`, `permissions()`, `hasPermission()`, and `actor()` for a
 * support session. Ask for the `organizations` scope to also get `organizations()`.
 */
readonly class CboxUser implements Principal
{
    use ReadsAuthorizationClaims;

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

    public function subjectId(): string
    {
        return $this->id;
    }

    public function claim(string $key): mixed
    {
        return $this->claims[$key] ?? null;
    }

    /**
     * Whether Cbox ID has verified this user's email (the OIDC `email_verified`
     * claim). Defaults to false when the claim is absent — treat an unverified or
     * unknown address as untrusted for account linking / adoption.
     */
    public function emailVerified(): bool
    {
        return ($this->claims['email_verified'] ?? false) === true;
    }
}
