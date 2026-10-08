<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

/** One operation of a spec, as the generator reads it. */
final class Operation
{
    /**
     * @param  list<string>  $name
     * @param  list<string>  $pathParams
     * @param  'body'|'query'|null  $inputKind
     * @param  array<string, mixed>|null  $inputSchema
     * @param  array<string, mixed>|null  $responseSchema
     * @param  'cursor'|'page'|null  $pagination
     */
    public function __construct(
        public array $name,
        public readonly ?string $action,
        public readonly ?string $operationId,
        public readonly string $method,
        public readonly string $path,
        public readonly array $pathParams,
        public readonly ?string $summary,
        public readonly ?string $description,
        public readonly ?string $scope,
        public readonly ?string $danger,
        public readonly bool $approval,
        public readonly ?string $inputKind,
        public readonly bool $inputRequired,
        public readonly ?array $inputSchema,
        public readonly ?array $responseSchema,
        public readonly ?string $pagination,
    ) {}

    /** Its key in the operation table: the action name, or its derived name. */
    public function key(): string
    {
        return $this->action ?? implode('.', $this->name);
    }
}
