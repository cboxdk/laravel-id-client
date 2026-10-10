<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * The social login buttons one sign-in page shows, after inheritance.
 *
 * `#/components/schemas/OfferedSocialProviders` on the environment plane.
 */
readonly class OfferedSocialProviders implements JsonSerializable
{
    public function __construct(
        /** @var list<OfferedSocialProvidersProvidersItem> */
        public array $providers,
        /**
         * The environment's providers this organization turned off for its page.
         *
         * @var list<string>
         */
        public array $notInherited,
        /** null for the plain sign-in page, before anybody has said who they are. */
        public ?string $organizationId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            providers: Field::required($data, 'providers', 'OfferedSocialProviders', Value::list(Value::dto(OfferedSocialProvidersProvidersItem::fromArray(...)))),
            notInherited: Field::required($data, 'not_inherited', 'OfferedSocialProviders', Value::list(Value::string(...))),
            organizationId: Field::optional($data, 'organization_id', 'OfferedSocialProviders', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'providers' => array_map(static fn (OfferedSocialProvidersProvidersItem $item) => $item->toArray(), $this->providers),
            'not_inherited' => $this->notInherited,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
