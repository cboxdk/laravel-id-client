<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Concerns\ReadsAuthorizationClaims;
use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Claims;

/**
 * The part of a sign-in worth remembering between requests.
 *
 * A {@see CboxUser} exists for one request — the callback — and carries tokens that do
 * not belong in a session. What the rest of the session needs is who this is and what
 * they may do: the subject, the organization and its tier, the roles and permissions,
 * and the actor of a support session. This is exactly that, and nothing else.
 *
 * It is what Cbox ID said AT SIGN-IN (or at the last {@see IdentityClient::refresh()}
 * with `remember: true`). A permission revoked since then is still here until the next
 * one — which is why the session is not where a high-stakes check should stop.
 */
readonly class Identity implements Principal
{
    use ReadsAuthorizationClaims;

    /**
     * The claims kept. An allow-list, so a claim that happens to ride on a token (an
     * `at_hash`, an `ent_*` entitlement, a custom claim with personal data in it) is not
     * copied into session storage just because it was there.
     */
    public const REMEMBERED_CLAIMS = [
        'sub', 'email', 'email_verified', 'name',
        'org', 'org_name', 'org_role', 'organizations',
        'roles', 'permissions', 'groups', 'act',
    ];

    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public string $subject,
        public array $claims = [],
    ) {}

    public static function fromPrincipal(Principal $principal): self
    {
        $claims = match (true) {
            $principal instanceof CboxUser, $principal instanceof VerifiedToken, $principal instanceof self => $principal->claims,
            default => self::claimsOf($principal),
        };

        return self::fromClaims(['sub' => $principal->subjectId()] + $claims) ?? new self($principal->subjectId());
    }

    /**
     * @param  array<array-key, mixed>  $claims
     */
    public static function fromClaims(array $claims): ?self
    {
        $subject = Claims::string($claims, 'sub');

        if ($subject === null) {
            return null;
        }

        $kept = [];

        foreach (self::REMEMBERED_CLAIMS as $key) {
            if (array_key_exists($key, $claims)) {
                $kept[$key] = $claims[$key];
            }
        }

        return new self($subject, $kept);
    }

    public function subjectId(): string
    {
        return $this->subject;
    }

    public function email(): ?string
    {
        return Claims::string($this->claims, 'email');
    }

    public function name(): ?string
    {
        return Claims::string($this->claims, 'name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['sub' => $this->subject] + $this->claims;
    }

    /**
     * @return array<string, mixed>
     */
    private static function claimsOf(Principal $principal): array
    {
        $organization = $principal->organization();
        $actor = $principal->actor();

        return array_filter([
            'org' => $organization?->id,
            'org_name' => $organization?->name,
            'org_role' => $organization?->role?->value,
            'roles' => $principal->roles(),
            'permissions' => $principal->permissions(),
            'act' => $actor !== null ? ['sub' => $actor->subject] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
