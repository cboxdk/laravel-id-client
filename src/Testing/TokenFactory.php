<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Enums\OrganizationRole;
use Firebase\JWT\JWT;
use Stringable;

/**
 * Mints real, signed Cbox ID access tokens for tests.
 *
 *     $token = CboxId::fake()->token()
 *         ->for('user_1')
 *         ->organization('org_1', 'Acme', OrganizationRole::Admin)
 *         ->permissions(['invoices:read'])
 *         ->scopes(['api.read']);
 *
 *     $this->withToken((string) $token)->getJson('/api/invoices')->assertOk();
 *
 * Signed with the key `CboxId::fake()` publishes, so `cbox-id.token` verifies it with
 * the production code path — signature, issuer, audience, expiry — rather than a mock
 * that waves everything through. The claim names are the ones Cbox ID's issuer mints.
 *
 * Immutable: every method returns a new factory, so a base token can be varied per test.
 */
class TokenFactory implements Stringable
{
    /**
     * @param  array<string, mixed>  $claims
     */
    final public function __construct(
        private readonly string $issuer,
        private readonly string $audience,
        private readonly string $clientId,
        private readonly array $claims = [],
    ) {}

    public function for(string $subject): static
    {
        return $this->with(['sub' => $subject]);
    }

    public function organization(string $id, ?string $name = null, OrganizationRole|string|null $role = null): static
    {
        return $this->with([
            'org' => $id,
            'org_name' => $name,
            'org_role' => $role instanceof OrganizationRole ? $role->value : $role,
        ]);
    }

    /** A token that acts for no organization — what a machine token looks like. */
    public function withoutOrganization(): static
    {
        return $this->with(['org' => null, 'org_name' => null, 'org_role' => null]);
    }

    /** @param list<string> $roles */
    public function roles(array $roles): static
    {
        return $this->with(['roles' => $roles]);
    }

    /** @param list<string> $permissions */
    public function permissions(array $permissions): static
    {
        return $this->with(['permissions' => $permissions]);
    }

    /** @param list<string> $scopes */
    public function scopes(array $scopes): static
    {
        return $this->with(['scope' => implode(' ', $scopes)]);
    }

    /** A support-session token: `act.sub` is the member of staff. */
    public function actor(string $subject): static
    {
        return $this->with(['act' => ['sub' => $subject]]);
    }

    public function audience(string $audience): static
    {
        return $this->with(['aud' => $audience]);
    }

    public function client(string $clientId): static
    {
        return $this->with(['client_id' => $clientId]);
    }

    public function expiresIn(int $seconds): static
    {
        return $this->with(['exp' => time() + $seconds]);
    }

    public function expired(): static
    {
        return $this->with(['iat' => time() - 3600, 'exp' => time() - 60]);
    }

    /** @param array<string, mixed> $claims */
    public function with(array $claims): static
    {
        return new static($this->issuer, $this->audience, $this->clientId, array_replace($this->claims, $claims));
    }

    /**
     * The claims the token will carry — nulls dropped, as the issuer drops them.
     *
     * @return array<string, mixed>
     */
    public function claims(): array
    {
        $now = time();

        return array_filter(array_replace([
            'iss' => $this->issuer,
            'sub' => 'user_test',
            'aud' => $this->audience,
            'client_id' => $this->clientId,
            'jti' => bin2hex(random_bytes(8)),
            'scope' => '',
            'iat' => $now,
            'exp' => $now + 300,
        ], $this->claims), static fn (mixed $value): bool => $value !== null);
    }

    public function mint(): string
    {
        return JWT::encode($this->claims(), TestKeys::pair()['private'], 'RS256', TestKeys::KID);
    }

    public function __toString(): string
    {
        return $this->mint();
    }
}
