<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * An access token whose signature, issuer and audience have been checked.
 *
 * Only ever constructed by {@see AccessTokenVerifier}. Holding one is the proof —
 * there is no path that produces one from an unverified string, which is what keeps
 * "did anyone check this?" from becoming a question anywhere downstream.
 */
readonly class VerifiedToken
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $entitlements  coarse, Claims-mode only
     * @param  array<string, mixed>  $claims  everything the token carried
     */
    public function __construct(
        public string $subject,
        public string $clientId,
        public ?string $organizationId,
        public ?string $organizationName,
        public string $audience,
        public array $scopes,
        public array $entitlements,
        public string $id,
        public int $expiresAt,
        public array $claims,
    ) {}

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * The organization this token acts for, or a failure.
     *
     * A client-credentials token minted for no organization carries `org: null`, and
     * a multi-tenant resource server that reads it as "any organization" has just
     * built a cross-tenant hole. Callers that need a tenant should ask for one here
     * rather than reaching for the nullable property and forgetting the null.
     */
    public function organizationOrFail(): string
    {
        return $this->organizationId
            ?? throw new \RuntimeException('This token acts for no organization; it cannot be used for tenant-scoped work.');
    }
}
