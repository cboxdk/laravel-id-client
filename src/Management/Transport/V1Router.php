<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Support\ApiUrl;

/**
 * Where a management call goes when `CBOX_ID_API_URL` is set: its `/v1` route, from
 * {@see V1RouteTable}. An operation the table does not move stays on `/api/v1`.
 *
 * Ids are passed on as the caller gave them, prefixed or bare: both Cbox ID APIs read either.
 *
 * @internal
 */
final readonly class V1Router
{
    private function __construct(
        /** `{api_url}/v1`. */
        public string $base,
    ) {}

    /** The router for `cbox-id-client.api_url`; null when it is not set. */
    public static function configured(): ?self
    {
        $configured = ApiUrl::configured();

        return $configured === '' ? null : self::for($configured);
    }

    public static function for(string $apiUrl): self
    {
        $base = ApiUrl::for('', $apiUrl)->base;
        $parts = parse_url($base);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $loopback = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);

        if ($host === '' || ($scheme !== 'https' && ! ($scheme === 'http' && $loopback))) {
            // Every management request carries a credential: never over plain http.
            throw ClientConfigurationException::because("`cbox-id-client.api_url` must be an https URL (got {$apiUrl}).");
        }

        return new self($base);
    }

    /**
     * The `/v1` route of a generated operation, `$legacyPath` its `/api/v1` path template.
     *
     * @param  list<string>  $legacyParams  the template's parameters, in order
     * @param  list<string>  $args  their values, in the same order
     */
    public function operation(Plane $plane, string $method, string $legacyPath, array $legacyParams, array $args): ?Endpoint
    {
        $route = self::routes($plane)[$method.' '.$legacyPath] ?? null;

        if ($route === null) {
            return null;
        }

        $values = [];

        foreach ($legacyParams as $index => $name) {
            $values[$name] = $args[$index] ?? '';
        }

        return $this->endpoint($route, array_map(rawurlencode(...), $values), $values);
    }

    /**
     * The `/v1` route of a concrete `/api/v1` path (`/organizations/01j…/members`), for the
     * hand-written client and the `request()` escape hatch. Segments arrive URL-encoded.
     */
    public function path(Plane $plane, string $method, string $path): ?Endpoint
    {
        $method = strtoupper($method);
        $segments = explode('/', trim($path, '/'));
        $best = null;
        $bestLiterals = -1;

        foreach (self::routes($plane) as $key => $route) {
            [$routeMethod, $template] = explode(' ', $key, 2);

            if ($routeMethod !== $method) {
                continue;
            }

            $parts = explode('/', trim($template, '/'));

            if (count($parts) !== count($segments)) {
                continue;
            }

            $values = [];
            $literals = 0;

            foreach ($parts as $index => $part) {
                if (preg_match('/\A\{([^}]+)\}\z/', $part, $match) === 1) {
                    if ($segments[$index] === '') {
                        continue 2;
                    }

                    $values[$match[1]] = $segments[$index];

                    continue;
                }

                if ($part !== $segments[$index]) {
                    continue 2;
                }

                $literals++;
            }

            if ($literals > $bestLiterals) {
                $best = [$route, $values];
                $bestLiterals = $literals;
            }
        }

        if ($best === null) {
            return null;
        }

        return $this->endpoint($best[0], $best[1], array_map(rawurldecode(...), $best[1]));
    }

    /**
     * The plane's moved operations, by `METHOD /api/v1 path`.
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: array<string, string>, 4: string}>
     */
    private static function routes(Plane $plane): array
    {
        // The generated table is too large for PHPStan to hold as one literal type; its
        // shape is the generator's to keep, and the route table test checks every entry.
        /** @var array<string, array<string, array{0: string, 1: string, 2: list<string>, 3: array<string, string>, 4: string}>> $routes */
        $routes = V1RouteTable::ROUTES; // @phpstan-ignore varTag.nativeType

        return $routes[$plane->value] ?? [];
    }

    /**
     * @param  array{0: string, 1: string, 2: list<string>, 3: array<string, string>, 4: string}  $route
     * @param  array<string, string>  $encoded  the `/api/v1` path parameters, URL-encoded
     * @param  array<string, string>  $decoded  the same, as values
     */
    private function endpoint(array $route, array $encoded, array $decoded): Endpoint
    {
        [$method, $path, $params, $query, $shape] = $route;

        foreach ($params as $name) {
            $position = strpos($path, '{}');

            if ($position !== false) {
                $path = substr_replace($path, $encoded[$name] ?? '', $position, 2);
            }
        }

        $extra = [];

        foreach ($query as $legacy => $public) {
            $extra[$public] = $decoded[$legacy] ?? '';
        }

        return new Endpoint($method, $this->base.$path, true, $shape, $extra);
    }
}
