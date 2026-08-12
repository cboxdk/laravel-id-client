<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * A social sign-in button to draw: a label and a provider key, never an internal id.
 */
readonly class SocialProvider
{
    public function __construct(
        public string $provider,
        public string $name,
    ) {}

    /** @param array<string, mixed> $entry */
    public static function fromArray(array $entry): ?self
    {
        $provider = $entry['provider'] ?? null;
        $name = $entry['name'] ?? null;

        if (! is_string($provider) || $provider === '') {
            return null;
        }

        return new self($provider, is_string($name) && $name !== '' ? $name : $provider);
    }
}
