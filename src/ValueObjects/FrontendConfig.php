<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * Everything needed to draw a sign-in box, and nothing that identifies anybody.
 *
 * A typed object rather than the raw array, because a Blade template reaching into
 * `$config['endpoints']['authorization']` has no way to be wrong out loud: a renamed field
 * renders an empty string into an href and the page looks fine until somebody clicks it.
 */
readonly class FrontendConfig
{
    /**
     * @param  array<string, string>  $endpoints
     * @param  list<SocialProvider>  $social
     * @param  array<string, mixed>  $appearance  the environment's theme, as the console saved it
     */
    public function __construct(
        public ?string $mode,
        public string $issuer,
        public array $endpoints,
        public array $social = [],
        public array $appearance = [],
    ) {}

    /** @param array<string, mixed> $document */
    public static function fromArray(array $document): self
    {
        return new self(
            mode: is_string($document['mode'] ?? null) ? $document['mode'] : null,
            issuer: is_string($document['issuer'] ?? null) ? $document['issuer'] : '',
            endpoints: self::endpointsIn($document['endpoints'] ?? null),
            social: self::providersIn($document['social'] ?? null),
            appearance: self::documentIn($document['appearance'] ?? null),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function endpointsIn(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $endpoints = [];

        foreach ($value as $key => $endpoint) {
            if (is_string($key) && is_string($endpoint)) {
                $endpoints[$key] = $endpoint;
            }
        }

        return $endpoints;
    }

    /**
     * @return list<SocialProvider>
     */
    private static function providersIn(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $providers = [];

        foreach ($value as $entry) {
            $provider = is_array($entry) ? SocialProvider::fromArray(self::documentIn($entry)) : null;

            if ($provider instanceof SocialProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * A string-keyed document, with anything else dropped.
     *
     * The wire is JSON somebody else produced, so "it is an array" is not the same claim as
     * "its keys are strings" — and the difference is what a static analyser is for.
     *
     * @return array<string, mixed>
     */
    private static function documentIn(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $document = [];

        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $document[$key] = $entry;
            }
        }

        return $document;
    }

    /** Whether this key drives real sign-ins. Handy for a "test mode" badge. */
    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    public function endpoint(string $key): ?string
    {
        return $this->endpoints[$key] ?? null;
    }

    /**
     * The environment's accent colour, if it set one.
     *
     * Reached through a method rather than left to the template, because the theme is a
     * nested document and the light mode's primary is the one a server-rendered box wants:
     * it sits inside the host page, which brings its own surface.
     */
    public function accent(): ?string
    {
        $light = $this->appearance['light'] ?? null;

        if (! is_array($light)) {
            return null;
        }

        $primary = $light['primary'] ?? null;

        return is_string($primary) && $primary !== '' ? $primary : null;
    }
}
