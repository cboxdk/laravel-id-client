<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

/**
 * The person's Cbox ID refresh token as the catalog keeps it: whose it is, and the
 * organization the login was bound to, because a refreshed token acts for that one only.
 */
final readonly class StoredRefreshToken
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public string $subject,
        public ?string $organizationId,
    ) {}

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['subject' => $this->subject, 'organizationId' => $this->organizationId];
    }
}
