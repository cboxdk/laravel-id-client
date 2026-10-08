<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `ManagementKeyRequireApproval` on the environment plane. */
readonly class ManagementKeyRequireApproval implements JsonSerializable
{
    public function __construct(
        /** One of `read`, `write`, `destructive`, `critical`. */
        public ?string $minDanger = null,
        /** @var list<string> */
        public ?array $actions = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            minDanger: Field::optional($data, 'min_danger', 'ManagementKeyRequireApproval', Value::string(...)),
            actions: Field::optional($data, 'actions', 'ManagementKeyRequireApproval', Value::list(Value::string(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'min_danger' => $this->minDanger,
            'actions' => $this->actions,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
