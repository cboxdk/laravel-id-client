<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

use RuntimeException;

/**
 * Generates one plane's typed client from its OpenAPI document: the client, one resource
 * class per action namespace, one readonly class per schema object a response carries,
 * and the operation table.
 *
 * It knows exactly the subset of JSON Schema the server's spec builder emits, and fails
 * loudly on anything it does not understand rather than guessing. The output is meant to
 * be read, and to pass Pint and PHPStan (level max) as it is written.
 */
final class PlaneGenerator
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private const DANGERS = ['read' => 'Read', 'write' => 'Write', 'destructive' => 'Destructive', 'critical' => 'Critical'];

    /** Class names a schema or resource cannot take as-is. */
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for',
        'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or',
        'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'return', 'self', 'static', 'string',
        'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
        // Names the generated files import.
        'apiresponse', 'calloptions', 'field', 'generator', 'jsonserializable', 'managementtransport',
        'operations', 'operationspec', 'page', 'pendingapprovalresult', 'returnpendingapproval', 'value', 'danger',
        'pagination', 'invalidargumentexception',
    ];

    /** @var array<string, array{schema: array<string, mixed>, source: string}> class name → object schema */
    private array $classes = [];

    /** @var list<string> */
    public array $skipped = [];

    /**
     * @param  array<string, mixed>  $spec
     */
    public function __construct(
        private readonly array $spec,
        private readonly PlaneConfig $config,
    ) {
        if (! is_string($spec['openapi'] ?? null) || ! str_starts_with($spec['openapi'], '3.')) {
            throw new RuntimeException("{$config->file}: not an OpenAPI 3 document");
        }
    }

    /**
     * Every generated file, keyed by its path relative to the package root.
     *
     * @return array<string, string>
     */
    public function render(): array
    {
        $operations = $this->operations();
        $files = [];
        $ns = $this->config->namespace;

        $files["src/Management/{$ns}/Operations.php"] = $this->renderOperations($operations);

        $root = new Node;

        foreach ($operations as $op) {
            $node = $root;

            foreach (array_slice($op->name, 0, -1) as $segment) {
                $node = $node->children[self::camel($segment)] ??= new Node(array_merge($node->path, [$segment]));
            }

            $node->ops[] = $op;
        }

        if ($root->ops !== []) {
            throw new RuntimeException("{$this->config->file}: an operation has a single-segment name: ".implode(', ', array_map(static fn (Operation $o): string => implode('.', $o->name), $root->ops)));
        }

        foreach ($root->children as $child) {
            $this->renderNode($child, $files);
        }

        $files["src/Management/{$this->config->className}.php"] = $this->renderClient($root);

        // Schema classes last: rendering operations registers them, and rendering one may
        // register more (an inline object inside it).
        $done = [];

        while (($pending = array_diff_key($this->classes, $done)) !== []) {
            foreach ($pending as $name => $class) {
                $files["src/Management/{$ns}/Schemas/{$name}.php"] = $this->renderSchema($name, $class['schema'], $class['source']);
                $done[$name] = true;
            }
        }

        ksort($files);

        return $files;
    }

    // ── Operations ──────────────────────────────────────────────────────────────────────

    /** @return list<Operation> */
    public function operations(): array
    {
        $paths = self::record($this->spec['paths'] ?? []);
        $defaultSecurity = $this->spec['security'] ?? [];
        $ops = [];

        foreach ($paths as $path => $rawItem) {
            $item = $this->deref($rawItem, 'pathItems');
            $shared = is_array($item['parameters'] ?? null) ? $item['parameters'] : [];

            foreach (self::METHODS as $method) {
                $op = $item[$method] ?? null;

                if (! is_array($op)) {
                    continue;
                }

                $security = is_array($op['security'] ?? null) ? $op['security'] : (is_array($defaultSecurity) ? $defaultSecurity : []);
                $schemes = [];

                foreach ($security as $requirement) {
                    foreach (array_keys(is_array($requirement) ? $requirement : []) as $scheme) {
                        $schemes[] = (string) $scheme;
                    }
                }

                if (array_intersect($schemes, $this->config->schemes) === []) {
                    $this->skipped[] = strtoupper($method)." {$path} (".($schemes === [] ? 'no security' : implode(', ', $schemes)).')';

                    continue;
                }

                $action = is_string($op['x-action'] ?? null) ? $op['x-action'] : null;
                $parameters = array_map(fn (mixed $p): array => $this->deref($p, 'parameters'), [...$shared, ...(is_array($op['parameters'] ?? null) ? $op['parameters'] : [])]);
                preg_match_all('/\{([^}]+)\}/', (string) $path, $matches);
                $pathParams = $matches[1];
                $query = array_values(array_filter($parameters, static fn (array $p): bool => ($p['in'] ?? null) === 'query'));
                $description = is_string($op['description'] ?? null) ? $op['description'] : null;

                // `x-scope` / `x-danger` are the contract. Routes the spec builder does not
                // generate from an action carry neither, and only say it in prose.
                $scope = is_string($op['x-scope'] ?? null) ? $op['x-scope'] : (preg_match('/Requires scope `([^`]+)`/', (string) $description, $m) === 1 ? $m[1] : null);
                $danger = is_string($op['x-danger'] ?? null) ? $op['x-danger'] : (preg_match('/Danger: ([a-z]+)/', (string) $description, $m) === 1 ? $m[1] : null);

                if ($danger !== null && ! isset(self::DANGERS[$danger])) {
                    throw new RuntimeException("{$this->config->file}: {$method} {$path} has an unknown danger {$danger}");
                }

                $responses = is_array($op['responses'] ?? null) ? $op['responses'] : [];
                $inputKind = null;
                $inputSchema = null;
                $inputRequired = false;

                if (isset($op['requestBody'])) {
                    $body = $this->deref($op['requestBody'], 'requestBodies');
                    $json = $body['content']['application/json'] ?? null;

                    if (! is_array($json)) {
                        throw new RuntimeException("{$this->config->file}: {$method} {$path} has a non-JSON body");
                    }

                    if ($query !== []) {
                        throw new RuntimeException("{$this->config->file}: {$method} {$path} has both a body and query parameters");
                    }

                    $inputKind = 'body';
                    $inputSchema = is_array($json['schema'] ?? null) ? $json['schema'] : [];
                    $resolved = isset($inputSchema['$ref']) ? $this->deref($inputSchema, 'schemas') : $inputSchema;
                    $inputRequired = ($body['required'] ?? false) === true && is_array($resolved['required'] ?? null) && $resolved['required'] !== [];
                } elseif ($query !== []) {
                    $inputKind = 'query';
                    $properties = [];
                    $required = [];

                    foreach ($query as $parameter) {
                        $name = (string) ($parameter['name'] ?? '');
                        $schema = is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [];

                        if (is_string($parameter['description'] ?? null)) {
                            $schema['description'] = $parameter['description'];
                        }

                        $properties[$name] = $schema;

                        if (($parameter['required'] ?? false) === true) {
                            $required[] = $name;
                        }
                    }

                    $inputSchema = ['type' => 'object', 'properties' => $properties, 'required' => $required];
                    $inputRequired = $required !== [];
                }

                $responseSchema = null;
                $hasResponse = false;

                foreach ([200, 201, 204] as $code) {
                    if (! isset($responses[$code])) {
                        continue;
                    }

                    $response = $this->deref($responses[$code], 'responses');
                    $json = $response['content']['application/json'] ?? null;
                    $responseSchema = is_array($json) && is_array($json['schema'] ?? null) ? $json['schema'] : null;
                    $hasResponse = true;

                    break;
                }

                if (! $hasResponse) {
                    throw new RuntimeException("{$this->config->file}: {$method} {$path} declares no 200, 201 or 204 response");
                }

                $queryNames = array_map(static fn (array $p): mixed => $p['name'] ?? null, $query);

                $ops[] = new Operation(
                    name: $action !== null ? explode('.', $action) : $this->derivedName($method, (string) $path),
                    action: $action,
                    operationId: is_string($op['operationId'] ?? null) ? $op['operationId'] : null,
                    method: strtoupper($method),
                    path: (string) $path,
                    pathParams: array_values($pathParams),
                    summary: is_string($op['summary'] ?? null) ? $op['summary'] : null,
                    description: $description,
                    scope: $scope,
                    danger: $danger,
                    approval: isset($responses[202]) && $this->isApprovalResponse($responses[202]),
                    inputKind: $inputKind,
                    inputRequired: $inputRequired,
                    inputSchema: $inputSchema,
                    responseSchema: $responseSchema,
                    pagination: in_array('after', $queryNames, true) ? 'cursor' : (in_array('page', $queryNames, true) ? 'page' : null),
                );
            }
        }

        // The account and platform planes name every action `account.…` / `platform.…`. The
        // client already says which plane it is: `$me->sessions->revokeOthers()`, not
        // `$me->account->sessions->revokeOthers()`. Only when EVERY action shares the prefix.
        $actions = array_filter($ops, static fn (Operation $o): bool => $o->action !== null);
        $strip = $actions !== [] && array_filter($actions, fn (Operation $o): bool => $o->name[0] !== $this->config->plane || count($o->name) <= 2) === [];

        if ($strip) {
            foreach ($actions as $op) {
                $op->name = array_slice($op->name, 1);
            }
        }

        usort($ops, static fn (Operation $a, Operation $b): int => strcmp(implode('.', $a->name), implode('.', $b->name)));

        $keys = [];

        foreach ($ops as $op) {
            if (isset($keys[$op->key()])) {
                throw new RuntimeException("{$this->config->file}: two operations are keyed {$op->key()}");
            }

            $keys[$op->key()] = true;
        }

        return $ops;
    }

    /** A name for a route that is not an action, from its path: `GET /apis/{id}` → `apis.get`. */
    private function derivedName(string $method, string $path): array
    {
        $prefix = $this->config->prefix;
        $rest = $prefix !== '' && str_starts_with($path, $prefix.'/') ? substr($path, strlen($prefix)) : $path;
        $segments = array_values(array_filter(explode('/', $rest), static fn (string $s): bool => $s !== ''));
        $resources = array_map(static fn (string $s): string => str_replace('-', '_', $s), array_values(array_filter($segments, static fn (string $s): bool => ! str_starts_with($s, '{'))));
        $onItem = $segments !== [] && str_starts_with($segments[count($segments) - 1], '{');
        $verb = match ($method) {
            'get' => $onItem ? 'get' : 'list',
            'post' => 'create',
            'put' => 'set',
            'patch' => 'update',
            'delete' => 'delete',
            default => $method,
        };

        return [...$resources, $verb];
    }

    private function isApprovalResponse(mixed $response): bool
    {
        if (is_array($response) && is_string($response['$ref'] ?? null)) {
            return str_ends_with($response['$ref'], '/ApprovalRequired');
        }

        return str_contains((string) json_encode($response), 'approval_required');
    }

    // ── Types ───────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|bool|null  $schema
     */
    private function type(mixed $schema, string $context): TypeRef
    {
        if ($schema === true || $schema === null || $schema === []) {
            return new TypeRef('mixed');
        }

        if (! is_array($schema)) {
            throw new RuntimeException("{$this->config->file}: unsupported schema ".json_encode($schema));
        }

        if (is_string($schema['$ref'] ?? null)) {
            $name = self::refName($schema['$ref']);
            $target = $this->deref($schema, 'schemas');
            $object = $this->objectSchema($target);

            if ($object !== null) {
                return new TypeRef('dto', class: $this->registerClass(self::className($name), $object, '#/components/schemas/'.$name));
            }

            return $this->type($target, $context);
        }

        if (array_key_exists('const', $schema)) {
            return new TypeRef(self::kindOfValue($schema['const']), enum: [$schema['const']]);
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (is_array($schema[$keyword] ?? null)) {
                $nullable = false;
                $rest = [];

                foreach ($schema[$keyword] as $alternative) {
                    if (is_array($alternative) && ($alternative['type'] ?? null) === 'null') {
                        $nullable = true;
                    } else {
                        $rest[] = $alternative;
                    }
                }

                if (count($rest) === 1) {
                    $type = $this->type($rest[0], $context);

                    return $type->withNullable($type->nullable || $nullable);
                }

                $objects = array_filter($rest, fn (mixed $alt): bool => is_array($alt) && $this->isObjectLike(isset($alt['$ref']) ? $this->deref($alt, 'schemas') : $alt));

                return count($objects) === count($rest) ? new TypeRef('map', $nullable) : new TypeRef('mixed');
            }
        }

        if (isset($schema['allOf'])) {
            $object = $this->objectSchema($schema);

            return $object === null ? new TypeRef('mixed') : new TypeRef('dto', class: $this->registerClass($context, $object, $context));
        }

        $types = is_array($schema['type'] ?? null) ? $schema['type'] : (is_string($schema['type'] ?? null) ? [$schema['type']] : []);
        $nullable = in_array('null', $types, true);
        $types = array_values(array_filter($types, static fn (mixed $t): bool => $t !== 'null'));
        $format = is_string($schema['format'] ?? null) ? $schema['format'] : null;

        if (is_array($schema['enum'] ?? null)) {
            $enum = array_values($schema['enum']);
            $kind = match ($types[0] ?? 'string') {
                'integer' => 'int',
                'number' => 'number',
                'boolean' => 'bool',
                default => 'string',
            };

            return new TypeRef($kind, $nullable || in_array(null, $enum, true), enum: $enum, format: $format);
        }

        if ($types === []) {
            if ($this->objectSchema($schema) !== null) {
                return new TypeRef('dto', $nullable, $this->registerClass($context, (array) $this->objectSchema($schema), $context));
            }

            return isset($schema['properties']) || isset($schema['additionalProperties']) ? new TypeRef('map', $nullable) : new TypeRef('mixed');
        }

        if (count($types) > 1) {
            return new TypeRef('mixed');
        }

        return match ($types[0]) {
            'string' => new TypeRef('string', $nullable, format: $format),
            'integer' => new TypeRef('int', $nullable),
            'number' => new TypeRef('number', $nullable),
            'boolean' => new TypeRef('bool', $nullable),
            'null' => new TypeRef('null'),
            'array' => new TypeRef('list', $nullable, item: $this->type($schema['items'] ?? true, $context.'Item')),
            'object' => ($object = $this->objectSchema($schema)) !== null
                ? new TypeRef('dto', $nullable, $this->registerClass($context, $object, $context))
                : new TypeRef('map', $nullable),
            default => throw new RuntimeException("{$this->config->file}: unsupported type {$types[0]}"),
        };
    }

    /**
     * The object a schema describes, when it is one worth a class: named properties and no
     * free-form members. `allOf` parts are merged. Null otherwise.
     *
     * @param  array<string, mixed>  $schema
     * @return array{properties: array<string, mixed>, required: list<string>, description: string|null}|null
     */
    private function objectSchema(array $schema): ?array
    {
        if (is_array($schema['allOf'] ?? null)) {
            $merged = ['properties' => [], 'required' => [], 'description' => is_string($schema['description'] ?? null) ? $schema['description'] : null];

            foreach ($schema['allOf'] as $part) {
                $resolved = is_array($part) && isset($part['$ref']) ? $this->deref($part, 'schemas') : (is_array($part) ? $part : []);
                $object = $this->objectSchema($resolved);

                if ($object === null) {
                    return null;
                }

                $merged['properties'] = [...$merged['properties'], ...$object['properties']];
                $merged['required'] = array_values(array_unique([...$merged['required'], ...$object['required']]));
                $merged['description'] ??= $object['description'];
            }

            return $merged['properties'] === [] ? null : $merged;
        }

        $types = is_array($schema['type'] ?? null) ? $schema['type'] : [$schema['type'] ?? null];

        if (! in_array('object', $types, true) && ! (($schema['type'] ?? null) === null && isset($schema['properties']))) {
            return null;
        }

        if (isset($schema['additionalProperties']) && $schema['additionalProperties'] !== false) {
            return null;
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        if ($properties === []) {
            return null;
        }

        return [
            'properties' => $properties,
            'required' => array_values(array_map('strval', is_array($schema['required'] ?? null) ? $schema['required'] : [])),
            'description' => is_string($schema['description'] ?? null) ? $schema['description'] : null,
        ];
    }

    /** @param array<string, mixed> $schema */
    private function isObjectLike(array $schema): bool
    {
        $types = is_array($schema['type'] ?? null) ? $schema['type'] : [$schema['type'] ?? null];

        return in_array('object', $types, true) || isset($schema['properties']) || isset($schema['allOf']);
    }

    /**
     * @param  array{properties: array<string, mixed>, required: list<string>, description: string|null}  $object
     */
    private function registerClass(string $name, array $object, string $source): string
    {
        if (isset($this->classes[$name]) && $this->classes[$name]['source'] !== $source) {
            throw new RuntimeException("{$this->config->file}: two schemas want the class name {$name} ({$this->classes[$name]['source']} and {$source})");
        }

        $this->classes[$name] ??= ['schema' => $object, 'source' => $source];

        return $name;
    }

    /** The result of an operation: the envelope's `data`, or the whole body without one. */
    private function resultType(Operation $op): TypeRef
    {
        if ($op->responseSchema === null) {
            return new TypeRef('null');
        }

        $schema = isset($op->responseSchema['$ref']) ? $op->responseSchema : $op->responseSchema;
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : null;
        $base = self::pascal($op->name);
        $data = $properties !== null && array_key_exists('data', $properties) ? $properties['data'] : $schema;

        if (is_array($data) && ($data['type'] ?? null) === 'array') {
            return new TypeRef('list', item: $this->type($data['items'] ?? true, $base.'Item'));
        }

        return $this->type($data, $base.'Result');
    }

    /**
     * A JSON Schema as a PHPStan array-shape type, for a body or query parameter.
     *
     * @param  array<string, mixed>|bool|null  $schema
     */
    private function shape(mixed $schema, int $depth = 0): string
    {
        if (! is_array($schema) || $schema === []) {
            return 'mixed';
        }

        if (isset($schema['$ref'])) {
            $target = $this->deref($schema, 'schemas');

            return $depth > 4 ? ($this->isObjectLike($target) ? 'array<string, mixed>' : 'mixed') : $this->shape($target, $depth + 1);
        }

        if (array_key_exists('const', $schema)) {
            return self::literal($schema['const']);
        }

        foreach (['oneOf', 'anyOf'] as $keyword) {
            if (is_array($schema[$keyword] ?? null)) {
                return implode('|', array_values(array_unique(array_map(fn (mixed $s): string => $this->shape($s, $depth + 1), $schema[$keyword]))));
            }
        }

        if (isset($schema['allOf'])) {
            $object = $this->objectSchema($schema);

            return $object === null ? 'array<string, mixed>' : $this->objectShape($object, $depth);
        }

        $types = is_array($schema['type'] ?? null) ? $schema['type'] : (is_string($schema['type'] ?? null) ? [$schema['type']] : []);
        $nullable = in_array('null', $types, true);
        $types = array_values(array_filter($types, static fn (mixed $t): bool => $t !== 'null'));

        if (is_array($schema['enum'] ?? null)) {
            $values = array_map(self::literal(...), array_values($schema['enum']));

            if ($nullable && ! in_array('null', $values, true)) {
                $values[] = 'null';
            }

            return implode('|', array_values(array_unique($values)));
        }

        if ($types === []) {
            $types = isset($schema['properties']) ? ['object'] : [];
        }

        if ($types === []) {
            return 'mixed';
        }

        $parts = array_map(fn (string $type): string => match ($type) {
            'string' => 'string',
            'integer' => 'int',
            'number' => 'int|float',
            'boolean' => 'bool',
            'array' => 'list<'.$this->shape($schema['items'] ?? true, $depth + 1).'>',
            'object' => ($object = $this->objectSchema($schema)) !== null ? $this->objectShape($object, $depth) : 'array<string, mixed>',
            default => throw new RuntimeException("{$this->config->file}: unsupported type {$type}"),
        }, $types);

        if ($nullable) {
            $parts[] = 'null';
        }

        return implode('|', $parts);
    }

    /**
     * @param  array{properties: array<string, mixed>, required: list<string>, description: string|null}  $object
     */
    private function objectShape(array $object, int $depth): string
    {
        $members = [];

        foreach ($object['properties'] as $key => $property) {
            $key = (string) $key;
            $name = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1 ? $key : self::literal($key);
            $members[] = $name.(in_array($key, $object['required'], true) ? '' : '?').': '.$this->shape($property, $depth + 1);
        }

        return 'array{'.implode(', ', $members).'}';
    }

    // ── Rendering ───────────────────────────────────────────────────────────────────────

    /**
     * @param  list<Operation>  $operations
     */
    private function renderOperations(array $operations): string
    {
        $ns = $this->config->namespace;
        $title = is_string($this->spec['info']['title'] ?? null) ? $this->spec['info']['title'] : $this->config->className;
        $imports = ['Cbox\Id\Client\Management\Transport\OperationSpec', 'InvalidArgumentException'];

        if (array_filter($operations, static fn (Operation $o): bool => $o->danger !== null) !== []) {
            $imports[] = 'Cbox\Id\Client\Management\Transport\Danger';
        }

        if (array_filter($operations, static fn (Operation $o): bool => $o->pagination !== null) !== []) {
            $imports[] = 'Cbox\Id\Client\Management\Transport\Pagination';
        }

        $out = $this->header("Cbox\\Id\\Client\\Management\\{$ns}", $imports);

        $out .= self::doc([
            "Every operation of the {$title} — method, path, scope, danger and whether it can be",
            'held for approval — keyed by action name.',
        ], '');
        $out .= "class Operations\n{\n";
        $out .= "    /** @var array<string, OperationSpec> */\n";
        $out .= "    private static array \$specs = [];\n\n";
        $out .= "    /** @return list<string> */\n";
        $out .= "    public static function keys(): array\n    {\n        return [\n";

        foreach ($operations as $op) {
            $out .= '            '.self::literal($op->key()).",\n";
        }

        $out .= "        ];\n    }\n\n";
        $out .= "    /** @return array<string, OperationSpec> */\n";
        $out .= "    public static function all(): array\n    {\n        \$all = [];\n\n";
        $out .= "        foreach (self::keys() as \$key) {\n            \$all[\$key] = self::spec(\$key);\n        }\n\n";
        $out .= "        return \$all;\n    }\n\n";
        $out .= "    public static function spec(string \$key): OperationSpec\n    {\n";
        $out .= "        return self::\$specs[\$key] ??= match (\$key) {\n";

        foreach ($operations as $op) {
            $args = [
                'action: '.($op->action === null ? 'null' : self::literal($op->action)),
                'operationId: '.($op->operationId === null ? 'null' : self::literal($op->operationId)),
                'method: '.self::literal($op->method),
                'path: '.self::literal($op->path),
                'pathParams: ['.implode(', ', array_map(self::literal(...), $op->pathParams)).']',
                'scope: '.($op->scope === null ? 'null' : self::literal($op->scope)),
                'danger: '.($op->danger === null ? 'null' : 'Danger::'.self::DANGERS[$op->danger]),
                'approval: '.($op->approval ? 'true' : 'false'),
                'body: '.($op->inputKind === 'body' ? 'true' : 'false'),
                'pagination: '.match ($op->pagination) {
                    'cursor' => 'Pagination::Cursor',
                    'page' => 'Pagination::Page',
                    default => 'null',
                },
            ];
            $out .= '            '.self::literal($op->key()).' => new OperationSpec('.implode(', ', $args)."),\n";
        }

        $out .= '            default => throw new InvalidArgumentException("No '.$this->config->plane." operation is keyed {\$key}.\"),\n";
        $out .= "        };\n    }\n}\n";

        return $out;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function renderNode(Node $node, array &$files): void
    {
        $ns = $this->config->namespace;
        $class = $this->resourceClass($node);
        $imports = ['Cbox\Id\Client\Management\Transport\ManagementTransport'];

        if ($node->ops !== []) {
            $imports[] = "Cbox\\Id\\Client\\Management\\{$ns}\\Operations";
        }
        $members = [];
        $body = '';

        // Class names this file already has in scope — its own and its children's — which an
        // imported schema of the same name (case-insensitively) would clash with.
        $taken = [strtolower($class) => true];

        foreach ($node->children as $child) {
            $taken[strtolower($this->resourceClass($child))] = true;
        }

        $claim = function (string $name, string $where) use (&$members, $node): string {
            if (isset($members[$name]) || isset($node->children[$name])) {
                throw new RuntimeException("{$this->config->file}: member {$name} collides at {$where}");
            }

            $members[$name] = true;

            return $name;
        };

        foreach ($node->ops as $op) {
            $leaf = $claim(self::camel($op->name[count($op->name) - 1]), implode('.', $op->name));
            $body .= $this->renderMethod($op, $leaf, $imports, $taken);

            if ($op->pagination !== null) {
                $body .= $this->renderAllMethod($op, $claim($leaf.'All', implode('.', $op->name).'All'), $imports, $taken);
            }
        }

        $properties = '';
        $constructor = '';

        foreach ($node->children as $key => $child) {
            $childClass = $this->resourceClass($child);
            $properties .= "    public readonly {$childClass} \${$key};\n\n";
            $constructor .= "        \$this->{$key} = new {$childClass}(\$transport);\n";
            $this->renderNode($child, $files);
        }

        $out = $this->header("Cbox\\Id\\Client\\Management\\{$ns}\\Resources", $imports);
        $out .= self::doc(['`'.implode('.', $node->path).'.*` on the '.$this->config->plane.' plane.'], '');
        $out .= "class {$class}\n{\n";
        $out .= $properties;

        if ($constructor === '') {
            $out .= "    public function __construct(private readonly ManagementTransport \$transport) {}\n";
        } else {
            $out .= "    public function __construct(private readonly ManagementTransport \$transport)\n    {\n{$constructor}    }\n";
        }

        $out .= $body;
        $out .= "}\n";

        $files["src/Management/{$ns}/Resources/{$class}.php"] = $out;
    }

    /**
     * @param  list<string>  $imports
     * @param  array<string, true>  $taken
     */
    private function renderMethod(Operation $op, string $name, array &$imports, array $taken): string
    {
        $params = [];
        $docParams = [];
        $args = [];

        foreach ($op->pathParams as $param) {
            $variable = self::variable($param);
            $params[] = "string \${$variable}";
            $args[] = "\${$variable}";
        }

        $input = $op->inputKind ?? null;

        if ($input !== null && $op->inputSchema !== null) {
            $params[] = 'array $'.$input.($op->inputRequired ? '' : ' = []');
            $docParams[] = '@param  '.$this->shape($op->inputSchema).'  $'.$input;
        }

        $params[] = '?CallOptions $options = null';
        $imports[] = 'Cbox\Id\Client\Management\Transport\CallOptions';
        $inputArg = $input === null ? '[]' : '$'.$input;
        $pathArgs = '['.implode(', ', $args).']';
        $spec = 'Operations::spec('.self::literal($op->key()).')';

        if ($op->pagination !== null) {
            $item = $this->importType($this->pageItem($op), $imports, $taken);
            $imports[] = 'Cbox\Id\Client\Management\Transport\Page';
            $result = 'Page<'.$item->docType().'>';
            $converter = $item->converter();
            $nativeReturn = 'Page';
            $callWait = "return \$this->transport->pageAndWait({$spec}, {$pathArgs}, {$inputArg}, \$options, {$converter});";
            $callDefer = "return \$this->transport->page({$spec}, {$pathArgs}, {$inputArg}, \$options, {$converter});";
        } else {
            $type = $this->importType($this->resultType($op), $imports, $taken);
            $imports[] = 'Cbox\Id\Client\Management\Transport\ApiResponse';
            $result = 'ApiResponse<'.$type->docType().'>';
            $converter = $type->converter();
            $nativeReturn = 'ApiResponse';
            $callWait = "return \$this->transport->callAndWait({$spec}, {$pathArgs}, {$inputArg}, \$options, {$converter});";
            $callDefer = "return \$this->transport->call({$spec}, {$pathArgs}, {$inputArg}, \$options, {$converter});";
        }

        $imports[] = 'Cbox\Id\Client\Management\Transport\Value';

        if ($op->approval) {
            $imports[] = 'Cbox\Id\Client\Management\Transport\PendingApprovalResult';
            $imports[] = 'Cbox\Id\Client\Management\Transport\ReturnPendingApproval';
            $docReturn = "@return (\$options is ReturnPendingApproval ? {$result}|PendingApprovalResult<{$result}> : {$result})";
            $nativeReturn .= '|PendingApprovalResult';
            $call = $callDefer;
        } else {
            $docReturn = "@return {$result}";
            $call = $callWait;
        }

        $lines = $this->methodSummary($op);
        $lines[] = '';
        $lines = [...$lines, ...$docParams, $docReturn];

        return "\n".self::doc($lines, '    ')
            ."    public function {$name}(".implode(', ', $params)."): {$nativeReturn}\n"
            ."    {\n"
            ."        {$call}\n"
            ."    }\n";
    }

    /**
     * @param  list<string>  $imports
     * @param  array<string, true>  $taken
     */
    private function renderAllMethod(Operation $op, string $name, array &$imports, array $taken): string
    {
        $params = [];
        $args = [];

        foreach ($op->pathParams as $param) {
            $variable = self::variable($param);
            $params[] = "string \${$variable}";
            $args[] = "\${$variable}";
        }

        $pageParameter = $op->pagination === 'cursor' ? 'after' : 'page';
        $schema = $op->inputSchema ?? ['type' => 'object', 'properties' => []];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        unset($properties[$pageParameter]);
        $schema['properties'] = $properties;
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $schema['required'] = array_values(array_filter($required, static fn (mixed $r): bool => $r !== $pageParameter));

        $params[] = 'array $query'.($schema['required'] === [] ? ' = []' : '');
        $params[] = '?CallOptions $options = null';
        $item = $this->importType($this->pageItem($op), $imports, $taken);
        $imports[] = 'Generator';

        $lines = [
            'Every item of `'.implode('.', $op->name).'`, fetching pages lazily as the iteration reaches them'
            .' — `'.$pageParameter.'` is followed for you. An approval is always waited on.',
            '',
            '@param  '.($properties === [] ? 'array<string, mixed>' : $this->shape($schema)).'  $query',
            '@return Generator<int, '.$item->docType().', mixed, void>',
        ];

        return "\n".self::doc($lines, '    ')
            ."    public function {$name}(".implode(', ', $params)."): Generator\n"
            ."    {\n"
            .'        return $this->transport->paginate(Operations::spec('.self::literal($op->key()).'), ['.implode(', ', $args).'], $query, $options, '.$item->converter().");\n"
            ."    }\n";
    }

    /** @return list<string> */
    private function methodSummary(Operation $op): array
    {
        $lines = [];
        $summary = trim((string) $op->summary);

        if ($summary !== '') {
            $lines = [...$lines, ...explode("\n", $summary)];
        }

        // The description's scope and danger sentences are the meta line below.
        $description = trim(preg_replace(['/Requires scope `[^`]+`\.\s*/', '/Danger: [a-z]+\.\s*/'], '', (string) $op->description) ?? '');

        if ($description !== '') {
            $lines[] = '';
            $lines = [...$lines, ...explode("\n", $description)];
        }

        $meta = '`'.$op->method.' '.$op->path.'`';
        $meta .= $op->action !== null ? ' · action `'.$op->action.'`' : '';
        $meta .= $op->scope !== null ? ' · scope `'.$op->scope.'`' : '';
        $meta .= $op->danger !== null ? ' · danger: '.$op->danger : '';
        $lines[] = '';
        $lines[] = $meta;

        if ($op->approval) {
            $lines[] = '';
            $lines[] = "May be held for a person's approval (`202 approval_required`): waited on, unless";
            $lines[] = '`$options` is `CallOptions::returnPendingApproval()`.';
        }

        return $lines;
    }

    private function pageItem(Operation $op): TypeRef
    {
        $type = $this->resultType($op);

        if ($type->kind !== 'list' || $type->item === null) {
            throw new RuntimeException("{$this->config->file}: paged ".implode('.', $op->name).' answers no data array');
        }

        return $type->item;
    }

    /**
     * Import the schema classes `$type` names, aliasing any that clash with a class already
     * in scope; returns the type as the file refers to it.
     *
     * @param  list<string>  $imports
     * @param  array<string, true>  $taken  lower-cased class names in scope
     */
    private function importType(TypeRef $type, array &$imports, array $taken = []): TypeRef
    {
        $aliases = [];

        for ($t = $type; $t !== null; $t = $t->item) {
            if ($t->kind === 'dto' && $t->class !== null) {
                $import = "Cbox\\Id\\Client\\Management\\{$this->config->namespace}\\Schemas\\{$t->class}";

                if (isset($taken[strtolower($t->class)])) {
                    $aliases[$t->class] = $t->class.'Schema';
                    $import .= " as {$t->class}Schema";
                }

                $imports[] = $import;
            }
        }

        return $type->aliased($aliases);
    }

    private function renderClient(Node $root): string
    {
        $ns = $this->config->namespace;
        $info = is_array($this->spec['info'] ?? null) ? $this->spec['info'] : [];
        $title = is_string($info['title'] ?? null) ? $info['title'] : $this->config->className;
        $description = is_string($info['description'] ?? null) ? explode("\n\n", trim($info['description']))[0] : '';
        $imports = [
            'Cbox\Id\Client\Management\Transport\ApprovalContext',
            'Cbox\Id\Client\Management\Transport\PendingApproval',
            'Cbox\Id\Client\Management\Transport\Plane',
            'Closure',
            'Illuminate\Http\Client\Factory',
        ];
        $properties = '';
        $assignments = '';

        foreach ($root->children as $key => $child) {
            $class = $this->resourceClass($child);
            $imports[] = "Cbox\\Id\\Client\\Management\\{$ns}\\Resources\\{$class}";
            $properties .= "    public readonly {$class} \${$key};\n\n";
            $assignments .= "        \$this->{$key} = new {$class}(\$this->transport);\n";
        }

        $servers = is_array($this->spec['servers'] ?? null) ? $this->spec['servers'] : [];
        $defaultBase = null;

        foreach ($servers as $server) {
            $url = is_array($server) ? ($server['url'] ?? null) : null;

            if (is_string($url) && ! str_contains($url, '{')) {
                $defaultBase = preg_replace('#/api/v1/?$#', '', $url);

                break;
            }
        }

        $out = $this->header('Cbox\Id\Client\Management', $imports);
        $out .= self::doc([
            $title.'.',
            '',
            ...explode("\n", $description),
            '',
            $defaultBase === null
                ? '`baseUrl` is required: the environment\'s own host.'
                : "`baseUrl` defaults to `{$defaultBase}`.",
            '',
            'GENERATED from `openapi/'.$this->config->file.'.yaml` by `composer generate`.',
        ], '');
        $out .= "class {$this->config->className} extends ManagementClient\n{\n";
        $out .= $properties;
        $out .= self::doc([
            'Exactly one of `$apiKey` and `$accessToken`. See {@see ManagementClient::__construct()}.',
            '',
            '@param  string|(Closure(): string)|null  $accessToken',
            '@param  (Closure(PendingApproval, ApprovalContext): void)|null  $onApprovalRequired',
            '@param  array<string, string>  $headers',
        ], '    ');
        $out .= <<<'PHP'
                public function __construct(
                    ?string $baseUrl = null,
                    #[\SensitiveParameter]
                    ?string $apiKey = null,
                    #[\SensitiveParameter]
                    string|Closure|null $accessToken = null,
                    ?string $environment = null,
                    ?Closure $onApprovalRequired = null,
                    int $approvalPollIntervalMs = 2000,
                    int $maxRetries = 3,
                    int $baseDelayMs = 500,
                    int $maxDelayMs = 30000,
                    float $timeout = 30,
                    array $headers = [],
                    ?Factory $http = null,
                ) {
                    parent::__construct($baseUrl, $apiKey, $accessToken, $environment, $onApprovalRequired, $approvalPollIntervalMs, $maxRetries, $baseDelayMs, $maxDelayMs, $timeout, $headers, $http);

            PHP;
        $out .= $assignments;
        $out .= "    }\n\n";
        $out .= "    public static function plane(): Plane\n    {\n        return Plane::".ucfirst($this->config->plane).";\n    }\n";

        if ($defaultBase !== null) {
            $out .= "\n    public static function defaultBaseUrl(): ?string\n    {\n        return ".self::literal($defaultBase).";\n    }\n";
        }

        $out .= "}\n";

        return $out;
    }

    /**
     * @param  array{properties: array<string, mixed>, required: list<string>, description: string|null}  $object
     */
    private function renderSchema(string $name, array $object, string $source): string
    {
        $ns = $this->config->namespace;
        $imports = [
            'Cbox\Id\Client\Management\Transport\Field',
            'Cbox\Id\Client\Management\Transport\Value',
            'JsonSerializable',
        ];
        $first = [];
        $later = [];
        $seen = [];

        foreach ($object['properties'] as $key => $property) {
            $key = (string) $key;
            $variable = self::property($key);

            if (isset($seen[$variable])) {
                throw new RuntimeException("{$this->config->file}: {$name} has two properties named {$variable}");
            }

            $seen[$variable] = true;
            $type = $this->type($property, $name.self::pascal([$key]));
            $required = in_array($key, $object['required'], true) && ! $type->nullable && $type->kind !== 'mixed';
            $entry = ['key' => $key, 'variable' => $variable, 'type' => $type, 'required' => $required, 'schema' => is_array($property) ? $property : []];

            if ($required) {
                $first[] = $entry;
            } else {
                $later[] = $entry;
            }
        }

        $entries = [...$first, ...$later];
        $specOrder = [...$first, ...$later];
        usort($specOrder, static fn (array $a, array $b): int => array_search($a['key'], array_map('strval', array_keys($object['properties'])), true) <=> array_search($b['key'], array_map('strval', array_keys($object['properties'])), true));
        $parameters = '';
        $reads = '';
        $exports = '';

        foreach ($entries as $entry) {
            /** @var TypeRef $type */
            $type = $entry['type'];
            $docLines = [];
            $description = is_string($entry['schema']['description'] ?? null) ? trim($entry['schema']['description']) : '';

            if ($description === '' && isset($entry['schema']['$ref'])) {
                $description = '';
            }

            if ($description !== '') {
                $docLines = explode("\n", $description);
            }

            if ($type->note() !== null) {
                $docLines[] = ($docLines === [] ? '' : '').$type->note();
            }

            if ($type->doc() !== null) {
                if ($docLines !== []) {
                    $docLines[] = '';
                }

                $docLines[] = '@var '.$type->doc();
            }

            if ($docLines !== []) {
                $parameters .= self::doc($docLines, '        ');
            }

            $native = $entry['required'] ? $type->native() : $type->withNullable(true)->native();
            $parameters .= "        public {$native} \${$entry['variable']}".($entry['required'] ? '' : ' = null').",\n";

            $reads .= "            {$entry['variable']}: ".($entry['required']
                ? 'Field::required($data, '.self::literal($entry['key']).', '.self::literal($name).', '.$type->converter().'),'
                : 'Field::optional($data, '.self::literal($entry['key']).', '.self::literal($name).', '.$type->bareConverter().'),')."\n";
            $this->importType($type, $imports);
        }

        foreach ($specOrder as $entry) {
            /** @var TypeRef $type */
            $type = $entry['type'];
            $exportType = $entry['required'] ? $type : $type->withNullable(true);
            $exports .= '            '.self::literal($entry['key']).' => '.$exportType->export('$this->'.$entry['variable']).",\n";
        }

        // Schema classes share a namespace: no imports of their own kind.
        $imports = array_values(array_filter($imports, static fn (string $i): bool => ! str_starts_with($i, "Cbox\\Id\\Client\\Management\\{$ns}\\Schemas\\")));

        $out = $this->header("Cbox\\Id\\Client\\Management\\{$ns}\\Schemas", $imports);
        $classDoc = $object['description'] !== null ? explode("\n", trim($object['description'])) : [];
        $classDoc[] = ($classDoc === [] ? '' : "\n").'`'.$source.'` on the '.$this->config->plane.' plane.';
        $out .= self::doc(array_merge(...array_map(static fn (string $l): array => explode("\n", $l), $classDoc)), '');
        $out .= "readonly class {$name} implements JsonSerializable\n{\n";
        $out .= "    public function __construct(\n{$parameters}    ) {}\n\n";
        $out .= "    /** @param  array<string, mixed>  \$data */\n";
        $out .= "    public static function fromArray(array \$data): self\n    {\n        return new self(\n{$reads}        );\n    }\n\n";
        $out .= "    /** @return array<string, mixed> */\n";
        $out .= "    public function toArray(): array\n    {\n        return [\n{$exports}        ];\n    }\n\n";
        $out .= "    /** @return array<string, mixed> */\n";
        $out .= "    public function jsonSerialize(): array\n    {\n        return \$this->toArray();\n    }\n";
        $out .= "}\n";

        return $out;
    }

    private function resourceClass(Node $node): string
    {
        return self::className(self::pascal($node->path));
    }

    /**
     * @param  list<string>  $imports
     */
    private function header(string $namespace, array $imports): string
    {
        $imports = array_values(array_unique($imports));
        usort($imports, static fn (string $a, string $b): int => strcasecmp($a, $b));
        $uses = implode('', array_map(static fn (string $i): string => "use {$i};\n", array_filter(
            $imports,
            static fn (string $i): bool => substr($i, 0, (int) strrpos($i, '\\')) !== $namespace,
        )));

        return "<?php\n\ndeclare(strict_types=1);\n\n"
            ."// GENERATED from openapi/{$this->config->file}.yaml by bin/generate-management. Do not edit.\n\n"
            ."namespace {$namespace};\n\n"
            .($uses !== '' ? $uses."\n" : '');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function deref(mixed $value, string $kind): array
    {
        if (is_array($value) && is_string($value['$ref'] ?? null)) {
            $name = self::refName($value['$ref']);
            $target = $this->spec['components'][$kind][$name] ?? null;

            if (! is_array($target)) {
                throw new RuntimeException("{$this->config->file}: unresolved {$value['$ref']}");
            }

            return $target;
        }

        if (! is_array($value)) {
            throw new RuntimeException("{$this->config->file}: expected an object, got ".json_encode($value));
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function record(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function refName(string $ref): string
    {
        $parts = explode('/', $ref);

        return end($parts) ?: '';
    }

    private static function kindOfValue(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'number',
            is_bool($value) => 'bool',
            default => 'string',
        };
    }

    public static function camel(string $segment): string
    {
        $camel = lcfirst(str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', $segment) ?? $segment)));

        return preg_match('/^[0-9]/', $camel) === 1 ? '_'.$camel : $camel;
    }

    /** @param list<string> $parts */
    public static function pascal(array $parts): string
    {
        return implode('', array_map(static fn (string $p): string => ucfirst(self::camel($p)), $parts));
    }

    private static function variable(string $name): string
    {
        $variable = self::camel($name);

        return in_array($variable, ['this', 'options', 'body', 'query', 'data'], true) ? $variable.'_' : $variable;
    }

    /** A schema property: any name but `$this` (its constructor has no other parameters). */
    private static function property(string $name): string
    {
        $property = self::camel($name);

        return $property === 'this' ? 'this_' : $property;
    }

    private static function className(string $name): string
    {
        $name = self::pascal([$name]);

        return in_array(strtolower($name), self::RESERVED, true) ? $name.'Schema' : $name;
    }

    private static function literal(mixed $value): string
    {
        if (is_string($value)) {
            return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) json_encode($value),
        };
    }

    /**
     * A docblock, `*\/` escaped and blank lines at either end dropped.
     *
     * @param  list<string>  $lines
     */
    public static function doc(array $lines, string $indent): string
    {
        $lines = array_map(static fn (string $l): string => rtrim(str_replace('*/', '*\/', $l)), $lines);

        while ($lines !== [] && $lines[0] === '') {
            array_shift($lines);
        }

        while ($lines !== [] && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        // Collapse runs of blank lines.
        $collapsed = [];

        foreach ($lines as $line) {
            if ($line === '' && $collapsed !== [] && $collapsed[count($collapsed) - 1] === '') {
                continue;
            }

            $collapsed[] = $line;
        }

        if ($collapsed === []) {
            return '';
        }

        if (count($collapsed) === 1) {
            return "{$indent}/** {$collapsed[0]} */\n";
        }

        return "{$indent}/**\n".implode('', array_map(static fn (string $l): string => $l === '' ? "{$indent} *\n" : "{$indent} * {$l}\n", $collapsed))."{$indent} */\n";
    }
}
