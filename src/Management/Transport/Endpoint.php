<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/**
 * Where one management request goes: `{issuer}/api/v1/…` as always, or its `/v1` route.
 *
 * @internal
 */
final readonly class Endpoint
{
    /**
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'|string  $method
     * @param  string  $url  without the query string
     * @param  bool  $v1  whether this is the `/v1` API, whose answers are read back into the `/api/v1` shape
     * @param  string  $shape  how `/api/v1` answers: `list` (`{"data": […], "meta": …}`), `data` (`{"data": …}`) or `bare`
     * @param  array<string, string>  $query  `/api/v1` path parameters that `/v1` takes in the query
     */
    public function __construct(
        public string $method,
        public string $url,
        public bool $v1 = false,
        public string $shape = 'data',
        public array $query = [],
    ) {}

    /** The origin (`scheme://host[:port]`) the request goes to. */
    public function origin(): string
    {
        $parts = parse_url($this->url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** This endpoint with the query string appended. */
    public function withQuery(string $query): self
    {
        return new self($this->method, $this->url.$query, $this->v1, $this->shape, []);
    }
}
