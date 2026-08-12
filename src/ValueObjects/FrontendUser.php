<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * The little a component needs to draw somebody: a label, an initial, an id.
 *
 * Deliberately not the whole user record. Everything else on one is either private or
 * somebody else's business, and a passthrough is how it leaks.
 */
readonly class FrontendUser
{
    public function __construct(
        public string $id,
        public ?string $email,
        public ?string $name,
    ) {}

    /** @param array<string, mixed> $user */
    public static function fromArray(array $user): ?self
    {
        $id = $user['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        return new self(
            id: $id,
            email: is_string($user['email'] ?? null) ? $user['email'] : null,
            name: is_string($user['name'] ?? null) ? $user['name'] : null,
        );
    }

    /** One or two letters for an avatar fallback, from whichever label exists. */
    public function initials(): string
    {
        $source = trim((string) ($this->name ?? $this->email ?? ''));

        if ($source === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $source) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr((string) $parts[0], 0, 1).mb_substr((string) $parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($source, 0, 1));
    }
}
