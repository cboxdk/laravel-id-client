<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * An organization in this environment, as the management API reports it.
 *
 * `attributes` is the whole object as received, for fields newer than this SDK.
 */
readonly class Organization
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug = null,
        public ?string $type = null,
        public ?string $status = null,
        public ?string $parentId = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            name: Claims::requiredString($data, 'name'),
            slug: Claims::string($data, 'slug'),
            type: Claims::string($data, 'type'),
            status: Claims::string($data, 'status'),
            parentId: Claims::string($data, 'parent_id'),
            attributes: $data,
        );
    }
}
