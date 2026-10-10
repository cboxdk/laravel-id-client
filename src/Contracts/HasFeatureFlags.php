<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

/**
 * A principal that can carry Cbox ID feature flags — the `feature_flags` claim on the ID
 * token, the access token and UserInfo when the app requests the `feature_flags` scope.
 *
 * A signed-in user, a verified bearer token and the identity remembered in the session
 * carry it. A customer API key does not, so `cbox-id.feature` and `@feature` treat one as
 * having no features on.
 */
interface HasFeatureFlags
{
    /** The scope that puts the `feature_flags` claim on the tokens and UserInfo. */
    public const SCOPE = 'feature_flags';

    /**
     * The keys of the flags on for this person in this organization, sorted as the server
     * sent them — or null when the claim is absent (the scope was not requested). `[]`
     * means it was asked and nothing is on.
     *
     * @return list<string>|null
     */
    public function featureFlags(): ?array;

    /** Whether the flag `$key` is on. Exact match; false when the claim is absent. */
    public function hasFeature(string $key): bool;
}
