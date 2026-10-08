<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/PortalLink` on the environment plane. */
readonly class PortalLink implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        /**
         * What the link may set up.
         *
         * @var list<string>
         */
        public array $intents,
        /** The address the link was mailed to, or null when it was not sent. */
        public ?string $emailedTo = null,
        /**
         * The one-time setup link — the whole credential. Shown once; `null` on an
         * idempotent replay of this answer.
         */
        public ?string $url = null,
        public ?string $expiresAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PortalLink', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'PortalLink', Value::string(...)),
            intents: Field::required($data, 'intents', 'PortalLink', Value::list(Value::string(...))),
            emailedTo: Field::optional($data, 'emailed_to', 'PortalLink', Value::string(...)),
            url: Field::optional($data, 'url', 'PortalLink', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'PortalLink', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'intents' => $this->intents,
            'emailed_to' => $this->emailedTo,
            'url' => $this->url,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
