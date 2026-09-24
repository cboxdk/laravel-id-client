<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/apps`. Two ways to describe an app:
 *
 * - **Short form** — `name` and `type` (`web`, `spa`, `cli`, `service`, `agent`), which
 *   decide the client type, grants and default scopes as the console does. `advanced`
 *   takes `clientType` and `grantTypes`.
 * - **From a blueprint** — {@see fromBlueprint()}: another environment's
 *   {@see AppBlueprint} (promote staging to production). `name`, `redirectUris` and
 *   `postLogoutRedirectUris` beside it replace the blueprint's own. A `private_key_jwt`
 *   blueprint needs `jwks`.
 *
 * Only what is set is sent.
 */
readonly class NewApp
{
    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     * @param  list<string>|null  $grantTypes
     * @param  list<string>|null  $scopes
     * @param  array<string, mixed>|null  $jwks  a public JWK Set
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = 'web',
        public array $redirectUris = [],
        public array $postLogoutRedirectUris = [],
        public ?string $clientType = null,
        public ?array $grantTypes = null,
        public ?array $scopes = null,
        public ?bool $firstParty = null,
        public ?string $organizationId = null,
        public ?array $jwks = null,
        public ?AppBlueprint $blueprint = null,
    ) {}

    /**
     * @param  list<string>  $redirectUris
     * @param  list<string>  $postLogoutRedirectUris
     * @param  array<string, mixed>|null  $jwks
     */
    public static function fromBlueprint(
        AppBlueprint $blueprint,
        ?string $name = null,
        array $redirectUris = [],
        array $postLogoutRedirectUris = [],
        ?string $organizationId = null,
        ?array $jwks = null,
    ): self {
        return new self(
            name: $name,
            type: null,
            redirectUris: $redirectUris,
            postLogoutRedirectUris: $postLogoutRedirectUris,
            organizationId: $organizationId,
            jwks: $jwks,
            blueprint: $blueprint,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'blueprint' => $this->blueprint?->document,
            'name' => $this->name,
            'type' => $this->type,
            'client_type' => $this->clientType,
            'grant_types' => $this->grantTypes,
            'redirect_uris' => $this->redirectUris,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUris,
            'scopes' => $this->scopes,
            'first_party' => $this->firstParty,
            'organization_id' => $this->organizationId,
            'jwks' => $this->jwks,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
