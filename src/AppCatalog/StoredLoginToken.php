<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

/**
 * The access token of the sign-in, kept sealed in the session until the first catalog
 * read exchanges it for a grant. Used once, then dropped.
 */
final readonly class StoredLoginToken
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public string $subject,
        public string $organizationId,
        public int $expiresAt,
    ) {}

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['subject' => $this->subject, 'organizationId' => $this->organizationId, 'expiresAt' => $this->expiresAt];
    }
}
