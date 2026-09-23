<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/apps`. `type` is the kind of client: `web` (a server that keeps a secret),
 * `spa`, `native` or `machine`.
 */
readonly class NewApp
{
    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     */
    public function __construct(
        public string $name,
        public string $type = 'web',
        public array $redirectUris = [],
        public array $postLogoutRedirectUris = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'redirect_uris' => $this->redirectUris,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUris,
        ], static fn (mixed $v): bool => $v !== []);
    }
}
