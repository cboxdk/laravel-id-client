<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/**
 * What the generator knows about one operation. Every plane lists its own in a generated
 * `Operations` class (e.g. `Environment\Operations::spec('apps.secrets.rotate')`), so
 * tooling can show an action's scope and danger — or ask for a confirmation before a
 * `critical` one — before running it.
 */
readonly class OperationSpec
{
    /**
     * @param  string|null  $action  the action name (`x-action`), e.g. `apps.secrets.rotate`; null for a route that is not an action
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'  $method
     * @param  string  $path  relative to `/api/v1`, with `{param}` placeholders
     * @param  list<string>  $pathParams  the path parameters, in the order a method takes them
     * @param  string|null  $scope  the scope the credential must carry, when the spec names one
     * @param  bool  $approval  whether the operation can answer `202 approval_required`
     * @param  bool  $body  whether the input travels as a JSON body (otherwise as the query string)
     */
    public function __construct(
        public ?string $action,
        public ?string $operationId,
        public string $method,
        public string $path,
        public array $pathParams = [],
        public ?string $scope = null,
        public ?Danger $danger = null,
        public bool $approval = false,
        public bool $body = false,
        public ?Pagination $pagination = null,
    ) {}

    /** Whether this is a write, and so carries an `Idempotency-Key`. */
    public function isWrite(): bool
    {
        return $this->method !== 'GET';
    }

    /** The name errors and decoding failures use: the action, else method and path. */
    public function name(): string
    {
        return $this->action ?? $this->method.' '.$this->path;
    }
}
