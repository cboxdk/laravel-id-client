<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * Whom the agent asks to act as.
 *
 * `AgentRequestSubject` on the environment plane.
 */
readonly class AgentRequestSubject implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** Null when that person no longer exists — the request can still be denied. */
        public ?string $email = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AgentRequestSubject', Value::string(...)),
            email: Field::optional($data, 'email', 'AgentRequestSubject', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
