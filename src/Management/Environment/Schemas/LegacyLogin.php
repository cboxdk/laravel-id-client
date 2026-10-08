<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * The legacy sign-in endpoint an app declared for migration. Its shared secret is never returned.
 *
 * `#/components/schemas/LegacyLogin` on the environment plane.
 */
readonly class LegacyLogin implements JsonSerializable
{
    public function __construct(
        public bool $declared,
        public bool $approved,
        public ?string $url = null,
        /** The app that declared it. */
        public ?string $clientId = null,
        /** That app's name. */
        public ?string $declaredBy = null,
        public ?string $approvedAt = null,
        /** The person or key that approved it. */
        public ?string $approvedBy = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            declared: Field::required($data, 'declared', 'LegacyLogin', Value::bool(...)),
            approved: Field::required($data, 'approved', 'LegacyLogin', Value::bool(...)),
            url: Field::optional($data, 'url', 'LegacyLogin', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'LegacyLogin', Value::string(...)),
            declaredBy: Field::optional($data, 'declared_by', 'LegacyLogin', Value::string(...)),
            approvedAt: Field::optional($data, 'approved_at', 'LegacyLogin', Value::string(...)),
            approvedBy: Field::optional($data, 'approved_by', 'LegacyLogin', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'declared' => $this->declared,
            'url' => $this->url,
            'client_id' => $this->clientId,
            'declared_by' => $this->declaredBy,
            'approved' => $this->approved,
            'approved_at' => $this->approvedAt,
            'approved_by' => $this->approvedBy,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
