<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `CustomDomainPending` on the workspace plane. */
readonly class CustomDomainPending implements JsonSerializable
{
    public function __construct(
        public string $domain,
        public string $recordName,
        public string $recordValue,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: Field::required($data, 'domain', 'CustomDomainPending', Value::string(...)),
            recordName: Field::required($data, 'record_name', 'CustomDomainPending', Value::string(...)),
            recordValue: Field::required($data, 'record_value', 'CustomDomainPending', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'record_name' => $this->recordName,
            'record_value' => $this->recordValue,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
