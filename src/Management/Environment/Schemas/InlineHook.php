<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/InlineHook` on the environment plane. */
readonly class InlineHook implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $url,
        /** One of `token_minting`, `post_login`, `pre_registration`, `post_registration`, `pre_password_change`, `post_password_change`. */
        public string $hookPoint,
        public bool $active,
        /** null when the environment owns it: it runs for EVERY organization. */
        public ?string $organizationId = null,
        public ?string $createdAt = null,
        /** The signing secret, on the create answer only — shown once. `null` on an idempotent replay. */
        public ?string $secret = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'InlineHook', Value::string(...)),
            url: Field::required($data, 'url', 'InlineHook', Value::string(...)),
            hookPoint: Field::required($data, 'hook_point', 'InlineHook', Value::string(...)),
            active: Field::required($data, 'active', 'InlineHook', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'InlineHook', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'InlineHook', Value::string(...)),
            secret: Field::optional($data, 'secret', 'InlineHook', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'hook_point' => $this->hookPoint,
            'organization_id' => $this->organizationId,
            'active' => $this->active,
            'created_at' => $this->createdAt,
            'secret' => $this->secret,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
