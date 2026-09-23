<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\ApiKeys\ApiKeyVerifier;
use Cbox\Id\Client\Exceptions\ApiKeyRejected;
use Cbox\Id\Client\Exceptions\ApiKeyVerificationUnavailable;
use Cbox\Id\Client\ValueObjects\VerifiedApiKey;

/**
 * Verifies a customer API key — one of YOUR customers' keys for YOUR API, minted in Cbox
 * ID's hosted UI — against Cbox ID. Bound to {@see ApiKeyVerifier};
 * `CboxId::fake()->apiKeys()` swaps in an in-memory one for tests.
 */
interface VerifiesApiKeys
{
    /**
     * @param  list<string>  $requiredPermissions  all must be held
     *
     * @throws ApiKeyRejected
     * @throws ApiKeyVerificationUnavailable
     */
    public function verify(string $key, array $requiredPermissions = []): VerifiedApiKey;
}
