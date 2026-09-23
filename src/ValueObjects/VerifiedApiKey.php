<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * A customer API key Cbox ID confirmed is live for this application.
 *
 * The key belongs to a person (`subject`) in an organization, and carries a subset of
 * that person's permissions for your app — re-capped by Cbox ID at every verify, so a
 * member who lost a permission cannot keep it through a key they minted earlier.
 *
 * A key never has an actor and never has app roles: it is a narrower credential than
 * a sign-in, by design.
 */
readonly class VerifiedApiKey implements Principal
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $keyId,
        public string $subject,
        public ?string $organizationId,
        public ?OrganizationRole $organizationRole,
        public array $permissions,
        public string $clientId,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}

    /**
     * From `POST /oauth/api-keys/verify`'s answer, or null when it is not an active key.
     *
     * @param  array<array-key, mixed>  $body
     */
    public static function fromResponse(array $body): ?self
    {
        if (($body['active'] ?? false) !== true) {
            return null;
        }

        $keyId = Claims::string($body, 'key_id');
        $subject = Claims::string($body, 'sub');

        if ($keyId === null || $subject === null) {
            return null;
        }

        $role = Claims::string($body, 'org_role');

        return new self(
            keyId: $keyId,
            subject: $subject,
            organizationId: Claims::string($body, 'org'),
            organizationRole: $role !== null ? OrganizationRole::tryFrom($role) : null,
            permissions: Claims::strings($body, 'permissions'),
            clientId: Claims::requiredString($body, 'client_id'),
            expiresAt: Claims::time($body, 'expires_at'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'active' => true,
            'key_id' => $this->keyId,
            'sub' => $this->subject,
            'org' => $this->organizationId,
            'org_role' => $this->organizationRole?->value,
            'permissions' => $this->permissions,
            'client_id' => $this->clientId,
            'expires_at' => $this->expiresAt?->getTimestamp(),
        ];
    }

    public function subjectId(): string
    {
        return $this->subject;
    }

    public function organization(): ?Organization
    {
        return $this->organizationId !== null
            ? new Organization($this->organizationId, null, $this->organizationRole)
            : null;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return [];
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return $permission !== '' && in_array($permission, $this->permissions, true);
    }

    public function hasRole(string $role): bool
    {
        return false;
    }

    public function actor(): ?Actor
    {
        return null;
    }

    public function isSupportSession(): bool
    {
        return false;
    }

    public function isExpired(?int $now = null): bool
    {
        // Carbon's clock rather than time(), so a test that travels in time moves this too.
        return $this->expiresAt !== null && $this->expiresAt->getTimestamp() <= ($now ?? Carbon::now()->getTimestamp());
    }
}
