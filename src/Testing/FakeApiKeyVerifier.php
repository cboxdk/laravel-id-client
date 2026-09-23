<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\VerifiesApiKeys;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;
use DateTimeImmutable;
use PHPUnit\Framework\Assert;

/**
 * Customer API keys in memory. Register the keys a test presents; anything else is
 * rejected exactly as Cbox ID would reject an unknown key.
 *
 *     CboxId::fake()->apiKeys()->add('ctx_live_abc', permissions: ['reports:read']);
 *     $this->withToken('ctx_live_abc')->getJson('/v1/reports')->assertOk();
 */
class FakeApiKeyVerifier implements VerifiesApiKeys
{
    /** @var array<string, VerifiedApiKey> */
    private array $keys = [];

    /** @var list<string> */
    private array $verified = [];

    private bool $unavailable = false;

    public function __construct(private readonly string $clientId = 'cid_test') {}

    /**
     * @param  list<string>  $permissions
     */
    public function add(
        string $key,
        string $subject = 'user_test',
        ?string $organization = 'org_test',
        ?OrganizationRole $role = OrganizationRole::Member,
        array $permissions = [],
        ?string $keyId = null,
        ?DateTimeImmutable $expiresAt = null,
    ): VerifiedApiKey {
        return $this->keys[$key] = new VerifiedApiKey(
            keyId: $keyId ?? 'key_'.substr(hash('sha256', $key), 0, 12),
            subject: $subject,
            organizationId: $organization,
            organizationRole: $organization !== null ? $role : null,
            permissions: $permissions,
            clientId: $this->clientId,
            expiresAt: $expiresAt,
        );
    }

    public function revoke(string $key): void
    {
        unset($this->keys[$key]);
    }

    /** Make every verification fail as if Cbox ID were unreachable. */
    public function unavailable(bool $unavailable = true): static
    {
        $this->unavailable = $unavailable;

        return $this;
    }

    public function verify(string $key, array $requiredPermissions = []): VerifiedApiKey
    {
        if ($this->unavailable) {
            throw ApiKeyVerificationUnavailable::unreachable();
        }

        $this->verified[] = $key;
        $verified = $this->keys[$key] ?? throw ApiKeyRejected::inactive();

        if ($verified->isExpired()) {
            throw ApiKeyRejected::inactive('The API key has expired.');
        }

        $missing = array_values(array_filter($requiredPermissions, static fn (string $p): bool => ! $verified->hasPermission($p)));

        return $missing === [] ? $verified : throw ApiKeyRejected::missingPermissions($missing);
    }

    public function assertVerified(string $key): void
    {
        Assert::assertContains($key, $this->verified, "The API key [{$key}] was never verified.");
    }

    public function assertNothingVerified(): void
    {
        Assert::assertSame([], $this->verified, 'An API key was verified unexpectedly.');
    }
}
