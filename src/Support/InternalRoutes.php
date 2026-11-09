<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

/**
 * Sends HTTPS calls to known public hosts to an address inside the cluster instead.
 *
 * Configured as `public-host=internal-base-url` pairs (`CBOX_INTERNAL_ROUTES`), e.g.
 * `cbox.cboxid.com=http://cbox-id.cbox-id.svc.cluster.local`. A request to
 * `https://cbox.cboxid.com/oauth/token` then leaves for
 * `http://cbox-id.cbox-id.svc.cluster.local/oauth/token`, with `Host: cbox.cboxid.com`
 * and `X-Forwarded-Proto: https`, `X-Forwarded-Host` and `X-Forwarded-Port: 443`, so
 * the receiving app still builds its public URLs.
 *
 * Only the transport changes. Everything above it (the issuer, discovery, token
 * `iss` and `aud`, the HTTPS checks) keeps seeing the public URL, because the
 * rewrite happens when the request is sent.
 *
 * Only an `https` request on the default port whose host equals a configured public
 * host exactly is rewritten; there are no wildcards. An internal base URL must be
 * `http` or `https`, with a host ending in `.svc` or `.svc.cluster.local` (or
 * `localhost` / `127.0.0.1` when the application runs in the `testing` environment),
 * and no path, query or credentials. Entries that break a rule are left out and
 * reported in {@see self::$rejected}.
 */
class InternalRoutes
{
    /**
     * @param  array<string, Uri>  $routes  public host => internal base URL
     * @param  list<string>  $rejected  why each left-out entry was left out
     */
    private function __construct(
        private readonly array $routes,
        public readonly array $rejected,
    ) {}

    /**
     * @param  mixed  $config  a `host=url,host=url` string, or an array of host => url
     * @param  bool  $allowLoopback  accept `localhost` and `127.0.0.1` as internal hosts (testing only)
     */
    public static function fromConfig(mixed $config, bool $allowLoopback = false): self
    {
        $routes = [];
        $rejected = [];

        foreach (self::pairs($config, $rejected) as [$publicHost, $internal]) {
            $publicHost = strtolower(trim($publicHost));
            $internal = trim($internal);

            if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $publicHost) !== 1) {
                $rejected[] = "Public host `{$publicHost}` is not a plain host name.";

                continue;
            }

            $reason = self::invalidInternal($internal, $allowLoopback);

            if ($reason !== null) {
                $rejected[] = "Internal URL for `{$publicHost}` {$reason}";

                continue;
            }

            $routes[$publicHost] = new Uri(rtrim($internal, '/'));
        }

        return new self($routes, $rejected);
    }

    public function isEmpty(): bool
    {
        return $this->routes === [];
    }

    /**
     * @return array<string, string> public host => internal base URL
     */
    public function all(): array
    {
        return array_map(static fn (Uri $uri): string => (string) $uri, $this->routes);
    }

    public function rewrite(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $host = strtolower($uri->getHost());

        if (strtolower($uri->getScheme()) !== 'https' || ! isset($this->routes[$host])) {
            return $request;
        }

        if ($uri->getPort() !== null && $uri->getPort() !== 443) {
            return $request;
        }

        $internal = $this->routes[$host];

        $target = $uri
            ->withScheme($internal->getScheme())
            ->withHost($internal->getHost())
            ->withPort($internal->getPort());

        return $request
            ->withUri($target)
            ->withHeader('Host', $host)
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', $host)
            ->withHeader('X-Forwarded-Port', '443');
    }

    /**
     * @param  list<string>  $rejected
     * @return list<array{string, string}>
     */
    private static function pairs(mixed $config, array &$rejected): array
    {
        if (is_array($config)) {
            $pairs = [];

            foreach ($config as $host => $url) {
                if (is_string($host) && is_string($url)) {
                    $pairs[] = [$host, $url];
                } else {
                    $rejected[] = 'An internal route must map a host name to a URL string.';
                }
            }

            return $pairs;
        }

        if (! is_string($config) || trim($config) === '') {
            return [];
        }

        $pairs = [];

        foreach (explode(',', $config) as $entry) {
            if (trim($entry) === '') {
                continue;
            }

            $parts = explode('=', $entry, 2);

            if (count($parts) !== 2) {
                $rejected[] = 'Entry `'.trim($entry).'` is not `public-host=internal-url`.';

                continue;
            }

            $pairs[] = [$parts[0], $parts[1]];
        }

        return $pairs;
    }

    private static function invalidInternal(string $url, bool $allowLoopback): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return "`{$url}` is not an absolute URL.";
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return "`{$url}` must use http or https.";
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '/')) {
            return "`{$url}` must be a bare base URL: no path, query or credentials.";
        }

        $host = strtolower($parts['host']);

        if (str_ends_with($host, '.svc') || str_ends_with($host, '.svc.cluster.local')) {
            return null;
        }

        if ($allowLoopback && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        return "`{$url}` is not a cluster service address (a host ending in .svc or .svc.cluster.local).";
    }
}
