<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

use Cbox\Id\Client\Management\Transport\OperationSpec;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds `src/Management/Transport/V1RouteTable.php`: where each generated management
 * operation lives on the `/v1` API, from the live `/v1` OpenAPI document (vendored, trimmed,
 * as `openapi/v1.json`) and the four `/api/v1` plane documents.
 *
 * An operation is matched on its plane and its action (`x-action`). Its `/api/v1` path
 * parameters are placed the way Cbox ID's own `V1Paths` places them: a parameter whose name
 * is a query parameter of the `/v1` route goes to the query; of the rest, the last ones fill
 * the `/v1` path parameters and any left over fill the remaining required query parameters.
 * An operation that cannot be placed that way, or whose `/v1` answer loses something the
 * `/api/v1` answer carries ({@see self::STAYS}), stays on `/api/v1`.
 */
final class V1RoutesGenerator
{
    public const string TARGET = 'src/Management/Transport/V1RouteTable.php';

    public const string SPEC = 'openapi/v1.json';

    /**
     * Operations kept on `/api/v1` although `/v1` serves them, because the `/v1` answer drops
     * something an app reads from the `/api/v1` one. Keyed `plane:action`.
     *
     * @var array<string, string>
     */
    public const array STAYS = [
        'workspace:environments.list' => '/v1 lists page by cursor only: meta.total, meta.page and meta.next_page are gone',
        'workspace:keys.workspace.list' => '/v1 lists page by cursor only: meta.total, meta.page and meta.next_page are gone',
        'workspace:team.list' => '/v1 lists page by cursor only: meta.total, meta.page and meta.next_page are gone',
        'account:account.apps.list' => '/v1 drops meta.revision and meta.refresh_after',
        'environment:signals.source.list' => '/v1 drops the source state, which /api/v1 answers in meta',
        'environment:signals.source.erasures.list' => '/v1 drops meta.generation and meta.commit_through',
        'environment:members.list' => '/v1 drops meta.directory_revision, which directory reconciliation reads (Page::$directoryRevision)',
        'environment:fga.tuples.list' => '/v1 drops meta.consistency_token',
        'environment:fga.resources.list' => '/v1 drops meta.consistency_token',
        'environment:fga.subjects.list' => '/v1 drops meta.consistency_token',
    ];

    /**
     * Operations the `/v1` document serves without an `x-action`, by `plane:key` — method and
     * path as Cbox ID's `V1Paths` moves them.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array UNNAMED = [
        'environment:action_approvals.get' => ['GET', '/approvals/{id}'],
        'workspace:action_approvals.get' => ['GET', '/approvals/{id}'],
        'account:action_approvals.get' => ['GET', '/me/approvals/{id}'],
        'platform:action_approvals.get' => ['GET', '/admin/approvals/{id}'],
        'environment:authenticator.me.list' => ['GET', '/me/authenticator'],
    ];

    /** The `/v1` operationId prefix and tag of each plane. */
    private const array PLANES = [
        'environment' => ['environment_', 'Environment', 'Environment'],
        'workspace' => ['organization_', 'Organization', 'Workspace'],
        'account' => ['account_', 'Account', 'Account'],
        'platform' => ['admin_', 'Admin', 'Platform'],
    ];

    /** @var list<string> */
    public array $notes = [];

    public function __construct(private readonly string $root) {}

    /**
     * Trim a full `/v1` document down to what the table is built from.
     *
     * @return array<string, mixed>
     */
    public static function trim(string $json): array
    {
        $doc = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($doc) || ! is_array($doc['paths'] ?? null)) {
            throw new RuntimeException('Not an OpenAPI document: no paths.');
        }

        $paths = [];

        foreach ($doc['paths'] as $path => $operations) {
            foreach (is_array($operations) ? $operations : [] as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true) || ! is_array($operation)) {
                    continue;
                }

                $parameters = [];

                foreach (is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [] as $parameter) {
                    if (is_array($parameter) && isset($parameter['name'], $parameter['in']) && in_array($parameter['in'], ['path', 'query'], true)) {
                        $parameters[] = ['name' => $parameter['name'], 'in' => $parameter['in'], 'required' => ($parameter['required'] ?? false) === true];
                    }
                }

                $paths[(string) $path][$method] = array_filter([
                    'operationId' => $operation['operationId'] ?? null,
                    'tags' => $operation['tags'] ?? null,
                    'x-action' => $operation['x-action'] ?? null,
                    'x-action-aliases' => $operation['x-action-aliases'] ?? null,
                    'parameters' => $parameters,
                ], static fn (mixed $v): bool => $v !== null && $v !== []);
            }
        }

        ksort($paths);

        return ['info' => ['title' => $doc['info']['title'] ?? '', 'version' => $doc['info']['version'] ?? ''], 'paths' => $paths];
    }

    /** The generated file's contents. */
    public function render(): string
    {
        $spec = json_decode((string) file_get_contents($this->root.'/'.self::SPEC), true, 512, JSON_THROW_ON_ERROR);
        $byAction = [];
        $byRoute = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $route = ['method' => strtoupper($method), 'path' => substr((string) $path, 3), 'parameters' => $operation['parameters'] ?? []];
                $byRoute[$route['method'].' '.$route['path']] = $route;
                $plane = $this->planeOf($operation);

                foreach ([$operation['x-action'] ?? null, ...($operation['x-action-aliases'] ?? [])] as $action) {
                    if (is_string($action) && $plane !== null) {
                        $byAction[$plane.':'.$action] = $route;
                    }
                }
            }
        }

        $routes = [];
        $stays = [];

        foreach (PlaneConfig::all() as $config) {
            /** @var class-string $operations */
            $operations = 'Cbox\\Id\\Client\\Management\\'.$config->namespace.'\\Operations';
            $shapes = $this->shapes($config);

            /** @var OperationSpec $op */
            foreach ($operations::all() as $key => $op) {
                $legacy = $op->method.' '.$op->path;
                $name = $config->plane.':'.$key;

                if (isset(self::STAYS[$name])) {
                    $stays[$config->plane][$legacy] = self::STAYS[$name];

                    continue;
                }

                $route = isset(self::UNNAMED[$name])
                    ? ($byRoute[self::UNNAMED[$name][0].' '.self::UNNAMED[$name][1]] ?? null)
                    : $byAction[$config->plane.':'.($op->action ?? $key)] ?? null;

                if ($route === null) {
                    $stays[$config->plane][$legacy] = 'no /v1 route';

                    continue;
                }

                $placed = $this->place($op, $route);

                if (is_string($placed)) {
                    $stays[$config->plane][$legacy] = $placed;
                    $this->notes[] = "{$name}: {$placed}";

                    continue;
                }

                $routes[$config->plane][$legacy] = [$route['method'], $placed['path'], $placed['params'], $placed['query'], $shapes[$legacy] ?? 'data'];
            }
        }

        return $this->php($routes, $stays, (string) ($spec['info']['version'] ?? ''));
    }

    /** Whether the file on disk is what {@see self::render()} makes. */
    public function stale(): bool
    {
        $path = $this->root.'/'.self::TARGET;

        return ! is_file($path) || file_get_contents($path) !== $this->render();
    }

    public function write(): void
    {
        file_put_contents($this->root.'/'.self::TARGET, $this->render());
    }

    /**
     * Where the `/api/v1` path parameters go on the `/v1` route.
     *
     * @param  array{method: string, path: string, parameters: list<array{name: string, in: string, required: bool}>}  $route
     * @return array{path: string, params: list<string>, query: array<string, string>}|string
     */
    private function place(OperationSpec $op, array $route): array|string
    {
        $pathNames = [];
        $queryNames = [];
        $required = [];

        foreach ($route['parameters'] as $parameter) {
            if ($parameter['in'] === 'path') {
                $pathNames[] = $parameter['name'];
            } else {
                $queryNames[] = $parameter['name'];

                if ($parameter['required']) {
                    $required[] = $parameter['name'];
                }
            }
        }

        preg_match_all('/\{([^}]+)\}/', $route['path'], $match);
        $pathNames = $match[1];
        $query = [];
        $rest = [];

        foreach ($op->pathParams as $legacy) {
            if (in_array($legacy, $queryNames, true) && ! in_array($legacy, $pathNames, true)) {
                $query[$legacy] = $legacy;
            } else {
                $rest[] = $legacy;
            }
        }

        if (count($rest) < count($pathNames)) {
            return 'the /v1 route needs more path parameters than /api/v1 has';
        }

        $forPath = array_slice($rest, count($rest) - count($pathNames));
        $leftover = array_slice($rest, 0, count($rest) - count($pathNames));
        $open = array_values(array_diff($required, array_values($query)));

        foreach ($leftover as $index => $legacy) {
            if (! isset($open[$index])) {
                return "/api/v1 path parameter {$legacy} has no place on the /v1 route";
            }

            $query[$legacy] = $open[$index];
        }

        $path = $route['path'];

        foreach ($pathNames as $name) {
            $path = str_replace('{'.$name.'}', '{}', $path);
        }

        return ['path' => $path, 'params' => $forPath, 'query' => $query];
    }

    /** @param array<string, mixed> $operation */
    private function planeOf(array $operation): ?string
    {
        $id = is_string($operation['operationId'] ?? null) ? $operation['operationId'] : '';
        $tag = is_string($operation['tags'][0] ?? null) ? $operation['tags'][0] : '';

        foreach (self::PLANES as $plane => [$prefix, $tagPrefix]) {
            if ($id !== '' ? str_starts_with($id, $prefix) : str_starts_with($tag, $tagPrefix.' ')) {
                return $plane;
            }
        }

        return null;
    }

    /**
     * How each `/api/v1` operation answers, by `METHOD /path`: `list` (`{"data": […], "meta": …}`),
     * `data` (`{"data": …}`) or `bare` (the value itself).
     *
     * @return array<string, string>
     */
    private function shapes(PlaneConfig $config): array
    {
        $doc = Yaml::parseFile($this->root.'/openapi/'.$config->file.'.yaml');
        $out = [];

        foreach ($doc['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $shape = null;

                foreach ($operation['responses'] ?? [] as $code => $response) {
                    $code = (string) $code;

                    if ($code[0] !== '2' || $code === '202') {
                        continue;
                    }

                    $schema = $this->resolve($doc, $response)['content']['application/json']['schema'] ?? null;

                    if ($schema === null) {
                        continue;
                    }

                    $shape = match (true) {
                        $this->has($doc, $schema, 'meta') => 'list',
                        $this->has($doc, $schema, 'data') => $shape === 'list' ? 'list' : 'data',
                        default => $shape ?? 'bare',
                    };
                }

                $out[strtoupper($method).' '.$path] = $shape ?? 'data';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>  $schema
     */
    private function has(array $doc, array $schema, string $property): bool
    {
        $schema = $this->resolve($doc, $schema);

        if (isset($schema['properties'][$property])) {
            return true;
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $combinator) {
            foreach ($schema[$combinator] ?? [] as $part) {
                if ($this->has($doc, $part, $property)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolve(array $doc, array $node): array
    {
        for ($depth = 0; isset($node['$ref']) && $depth < 20; $depth++) {
            $target = $doc;

            foreach (array_slice(explode('/', (string) $node['$ref']), 1) as $part) {
                $target = $target[$part] ?? [];
            }

            $node = is_array($target) ? $target : [];
        }

        return $node;
    }

    /**
     * @param  array<string, array<string, array{0: string, 1: string, 2: list<string>, 3: array<string, string>, 4: string}>>  $routes
     * @param  array<string, array<string, string>>  $stays
     */
    private function php(array $routes, array $stays, string $version): string
    {
        $out = "<?php\n\ndeclare(strict_types=1);\n\n";
        $out .= "// GENERATED from openapi/v1.json and openapi/*.yaml by bin/generate-v1-routes. Do not edit.\n\n";
        $out .= "namespace Cbox\\Id\\Client\\Management\\Transport;\n\n";
        $out .= "/**\n * Where each `/api/v1` management operation lives on the `/v1` API (document version {$version}).\n *\n";
        $out .= " * `ROUTES[plane]['METHOD /api/v1 path']` is `[method, /v1 path, path parameters, query, shape]`:\n";
        $out .= " * the `/v1` path has a `{}` for each `/api/v1` path parameter named in the third entry, in order;\n";
        $out .= " * the fourth maps the other `/api/v1` path parameters to the `/v1` query parameter that takes\n";
        $out .= " * them; the fifth is how `/api/v1` answers: `list` (`{\"data\": […], \"meta\": …}`), `data`\n";
        $out .= " * (`{\"data\": …}`) or `bare` (the value itself).\n *\n";
        $out .= " * `STAYS` lists what stays on `/api/v1`, and why.\n *\n * @internal\n */\n";
        $out .= "final class V1RouteTable\n{\n";
        $out .= "    /** @var array<string, array<string, array{0: string, 1: string, 2: list<string>, 3: array<string, string>, 4: 'list'|'data'|'bare'}>> */\n";
        $out .= "    public const array ROUTES = [\n";

        foreach ($routes as $plane => $entries) {
            ksort($entries);
            $out .= "        '{$plane}' => [\n";

            foreach ($entries as $legacy => [$method, $path, $params, $query, $shape]) {
                $out .= '            '.self::export($legacy).' => ['.self::export($method).', '.self::export($path).', '.self::export($params).', '.self::export($query).', '.self::export($shape)."],\n";
            }

            $out .= "        ],\n";
        }

        $out .= "    ];\n\n";
        $out .= "    /** @var array<string, array<string, string>> */\n";
        $out .= "    public const array STAYS = [\n";

        foreach ($stays as $plane => $entries) {
            ksort($entries);
            $out .= "        '{$plane}' => [\n";

            foreach ($entries as $legacy => $why) {
                $out .= '            '.self::export($legacy).' => '.self::export($why).",\n";
            }

            $out .= "        ],\n";
        }

        return $out."    ];\n}\n";
    }

    private static function export(mixed $value): string
    {
        if (is_string($value)) {
            return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];

            foreach ($value as $key => $item) {
                $parts[] = array_is_list($value) ? self::export($item) : self::export((string) $key).' => '.self::export($item);
            }

            return '['.implode(', ', $parts).']';
        }

        return var_export($value, true);
    }
}
