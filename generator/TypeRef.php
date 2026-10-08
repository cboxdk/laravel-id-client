<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

/**
 * A JSON Schema, as the PHP type a generated schema property or result has — and the
 * code that converts a decoded value to it and back.
 */
final readonly class TypeRef
{
    /**
     * @param  'string'|'int'|'number'|'bool'|'mixed'|'map'|'dto'|'list'|'null'  $kind
     * @param  list<scalar|null>  $enum
     */
    public function __construct(
        public string $kind,
        public bool $nullable = false,
        public ?string $class = null,
        public ?self $item = null,
        public array $enum = [],
        public ?string $format = null,
    ) {}

    /**
     * The same type with schema classes renamed — for a file that imports one under an alias.
     *
     * @param  array<string, string>  $aliases
     */
    public function aliased(array $aliases): self
    {
        return new self(
            $this->kind,
            $this->nullable,
            $this->class !== null ? ($aliases[$this->class] ?? $this->class) : null,
            $this->item?->aliased($aliases),
            $this->enum,
            $this->format,
        );
    }

    public function withNullable(bool $nullable): self
    {
        return new self($this->kind, $nullable, $this->class, $this->item, $this->enum, $this->format);
    }

    /** The native PHP type of a property holding it. */
    public function native(): string
    {
        $base = match ($this->kind) {
            'string' => 'string',
            'int' => 'int',
            'number' => 'int|float',
            'bool' => 'bool',
            'mixed' => 'mixed',
            'map', 'list' => 'array',
            'dto' => (string) $this->class,
            'null' => 'null',
        };

        if (! $this->nullable || in_array($this->kind, ['mixed', 'null'], true)) {
            return $base;
        }

        return str_contains($base, '|') ? $base.'|null' : '?'.$base;
    }

    /** The PHPDoc type, where it says more than the native one (arrays); null otherwise. */
    public function doc(): ?string
    {
        if (! in_array($this->kind, ['map', 'list'], true)) {
            return null;
        }

        return $this->docType();
    }

    public function docType(): string
    {
        $base = match ($this->kind) {
            'map' => 'array<string, mixed>',
            'list' => 'list<'.($this->item?->docType() ?? 'mixed').'>',
            default => $this->native(),
        };

        if (in_array($this->kind, ['map', 'list'], true) && $this->nullable) {
            return $base.'|null';
        }

        return $base;
    }

    /** A `Closure(mixed, string): T` converting a decoded value to this type. */
    public function converter(): string
    {
        $converter = match ($this->kind) {
            'string' => 'Value::string(...)',
            'int' => 'Value::int(...)',
            'number' => 'Value::number(...)',
            'bool' => 'Value::bool(...)',
            'mixed' => 'Value::mixed(...)',
            'map' => 'Value::object(...)',
            'null' => 'Value::none(...)',
            'dto' => 'Value::dto('.$this->class.'::fromArray(...))',
            'list' => 'Value::list('.($this->item?->converter() ?? 'Value::mixed(...)').')',
        };

        return $this->nullable && ! in_array($this->kind, ['mixed', 'null'], true) ? "Value::nullable({$converter})" : $converter;
    }

    /** The bare converter, ignoring nullability — for a property read with `Field::optional()`. */
    public function bareConverter(): string
    {
        return $this->withNullable(false)->converter();
    }

    public function containsDto(): bool
    {
        return $this->kind === 'dto' || ($this->kind === 'list' && $this->item?->containsDto() === true);
    }

    /** `$expr`, in its JSON form again — schema objects back to arrays. */
    public function export(string $expr): string
    {
        if ($this->kind === 'dto') {
            return $this->nullable ? "{$expr}?->toArray()" : "{$expr}->toArray()";
        }

        if ($this->kind === 'list' && $this->item !== null && $this->item->containsDto()) {
            $map = 'array_map(static fn ('.$this->item->native().' $item) => '.$this->item->export('$item').", {$expr})";

            return $this->nullable ? "{$expr} === null ? null : {$map}" : $map;
        }

        return $expr;
    }

    /** Words for the docs: the enum values, when there are some. */
    public function note(): ?string
    {
        if ($this->enum === []) {
            return null;
        }

        $values = array_map(static fn (mixed $v): string => '`'.(is_string($v) ? $v : json_encode($v)).'`', array_values(array_filter($this->enum, static fn (mixed $v): bool => $v !== null)));

        return 'One of '.implode(', ', $values).'.';
    }
}
