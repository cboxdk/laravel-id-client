<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `FgaTypeRelationsItem` on the environment plane. */
readonly class FgaTypeRelationsItem implements JsonSerializable
{
    public function __construct(
        public string $name,
        /**
         * How the relation is decided, as a tree of one-key objects: `direct` (a list of
         * `{type, relation?}`), `computed` (a relation name), `from` (`{tupleset, relation}`),
         * `union` and `intersection` (lists of rewrites), `exclusion` (`{base, subtract}`).
         *
         * @var array<string, mixed>
         */
        public array $rewrite,
        /**
         * The subjects a tuple may name on this relation; empty for a relation that is only computed.
         *
         * @var list<FgaTypeRelationsItemDirectlyRelatedItem>
         */
        public array $directlyRelated,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Field::required($data, 'name', 'FgaTypeRelationsItem', Value::string(...)),
            rewrite: Field::required($data, 'rewrite', 'FgaTypeRelationsItem', Value::object(...)),
            directlyRelated: Field::required($data, 'directly_related', 'FgaTypeRelationsItem', Value::list(Value::dto(FgaTypeRelationsItemDirectlyRelatedItem::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'rewrite' => $this->rewrite,
            'directly_related' => array_map(static fn (FgaTypeRelationsItemDirectlyRelatedItem $item) => $item->toArray(), $this->directlyRelated),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
