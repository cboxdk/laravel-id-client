<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Exceptions\ApprovalDenied;
use Cbox\Id\Client\Exceptions\ApprovalException;
use Cbox\Id\Client\Exceptions\ApprovalExpired;
use Cbox\Id\Client\Exceptions\CboxIdApiException;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\ManagementNetworkException;
use Cbox\Id\Client\Exceptions\UnexpectedResponse;
use Closure;
use Generator;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;

/**
 * The hand-written core every generated management client runs on: authentication,
 * idempotency keys, retries, the approval loop, error typing and pagination. Generated
 * code only describes operations; it never talks to the network itself.
 *
 * - **Idempotency.** Every POST, PUT, PATCH and DELETE carries an `Idempotency-Key` — a
 *   fresh UUID unless the call names one — and every retry of it carries the SAME key, so
 *   a write lands once however often it is sent.
 * - **Retries.** Network failures, 5xx, 429 and `409 idempotency_in_progress` are retried
 *   with exponential backoff and jitter; `Retry-After` is honoured, and one longer than
 *   `maxDelayMs` is thrown rather than waited out.
 * - **Approvals.** A `202 approval_required` calls `onApprovalRequired`, polls the
 *   approval (on this client's own origin only — the poll carries the credential), and
 *   repeats the request with `Cbox-Approval` and the same key once it is approved.
 * - **Errors.** Any other non-2xx is a {@see CboxIdApiException}; no answer at all, after
 *   the retries, is a {@see ManagementNetworkException}.
 *
 * It never logs, and never puts a request body in an exception.
 */
class ManagementTransport
{
    /** How many approvals one call goes through before giving up — a policy-loop guard. */
    public const MAX_APPROVAL_ROUNDS = 3;

    /** Where requests go: the plane's host, ending in `/api/v1`. */
    public readonly string $baseUrl;

    private readonly string $origin;

    public function __construct(
        public readonly Plane $plane,
        private readonly ClientOptions $options,
        ?string $defaultBaseUrl = null,
    ) {
        $baseUrl = $options->baseUrl ?? $defaultBaseUrl;

        if ($baseUrl === null || $baseUrl === '') {
            throw ClientConfigurationException::because("The {$plane->value} management client needs a `baseUrl` (the environment's own host).");
        }

        $this->assertCredentials($plane, $options);

        [$this->origin, $this->baseUrl] = self::normalizeBaseUrl($baseUrl);
    }

    /**
     * Run one operation. On `202 approval_required` it waits for the approval — unless
     * `$options` is {@see ReturnPendingApproval}, when the pending approval is returned.
     *
     * @template T
     *
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $input  the JSON body, or the query for a read
     * @param  Closure(mixed, string): T  $decode  turns the envelope's `data` into the result
     * @return ($options is ReturnPendingApproval ? ApiResponse<T>|PendingApprovalResult<ApiResponse<T>> : ApiResponse<T>)
     */
    public function call(OperationSpec $op, array $pathArgs, array $input, ?CallOptions $options, Closure $decode): ApiResponse|PendingApprovalResult
    {
        $finish = fn (Response $response, ?string $key): ApiResponse => $this->result($response, $key, $op->name(), $decode);

        if ($options instanceof ReturnPendingApproval) {
            return $this->deferring($op, $this->url($op, $pathArgs, $op->body ? [] : $input), $op->body ? $input : null, $options, $finish);
        }

        return $this->waiting($op, $this->url($op, $pathArgs, $op->body ? [] : $input), $op->body ? $input : null, $options, $finish);
    }

    /**
     * {@see self::call()} for an operation that is never held for approval: it always
     * answers an {@see ApiResponse}.
     *
     * @template T
     *
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $input
     * @param  Closure(mixed, string): T  $decode
     * @return ApiResponse<T>
     */
    public function callAndWait(OperationSpec $op, array $pathArgs, array $input, ?CallOptions $options, Closure $decode): ApiResponse
    {
        return $this->waiting(
            $op,
            $this->url($op, $pathArgs, $op->body ? [] : $input),
            $op->body ? $input : null,
            $options,
            fn (Response $response, ?string $key): ApiResponse => $this->result($response, $key, $op->name(), $decode),
        );
    }

    /**
     * One page of a paged list.
     *
     * @template T
     *
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $query
     * @param  Closure(mixed, string): T  $item  converts one item of `data`
     * @return ($options is ReturnPendingApproval ? Page<T>|PendingApprovalResult<Page<T>> : Page<T>)
     */
    public function page(OperationSpec $op, array $pathArgs, array $query, ?CallOptions $options, Closure $item): Page|PendingApprovalResult
    {
        $finish = fn (Response $response, ?string $key): Page => $this->toPage($this->result($response, $key, $op->name(), Value::list($item)));

        if ($options instanceof ReturnPendingApproval) {
            return $this->deferring($op, $this->url($op, $pathArgs, $query), null, $options, $finish);
        }

        return $this->waiting($op, $this->url($op, $pathArgs, $query), null, $options, $finish);
    }

    /**
     * {@see self::page()}, always waiting out an approval.
     *
     * @template T
     *
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $query
     * @param  Closure(mixed, string): T  $item
     * @return Page<T>
     */
    public function pageAndWait(OperationSpec $op, array $pathArgs, array $query, ?CallOptions $options, Closure $item): Page
    {
        return $this->waiting(
            $op,
            $this->url($op, $pathArgs, $query),
            null,
            $options,
            fn (Response $response, ?string $key): Page => $this->toPage($this->result($response, $key, $op->name(), Value::list($item))),
        );
    }

    /**
     * Every item of a paged list, fetching pages lazily as the iteration reaches them.
     * Follows `meta.next_cursor` (as `after`) or `meta.next_page` (as `page`). An approval
     * is always waited on.
     *
     * @template T
     *
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $query
     * @param  Closure(mixed, string): T  $item
     * @return Generator<int, T, mixed, void>
     */
    public function paginate(OperationSpec $op, array $pathArgs, array $query, ?CallOptions $options, Closure $item): Generator
    {
        for (; ;) {
            $page = $this->pageAndWait($op, $pathArgs, $query, $options, $item);

            foreach ($page->items as $entry) {
                yield $entry;
            }

            if ($page->items === [] || ! $page->hasMore) {
                return;
            }

            if ($op->pagination === Pagination::Page) {
                $current = $query['page'] ?? 1;
                $query['page'] = $page->nextPage ?? (is_numeric($current) ? (int) $current + 1 : 2);

                continue;
            }

            if ($page->nextCursor === null || $page->nextCursor === '') {
                return;
            }

            $query['after'] = $page->nextCursor;
        }
    }

    /**
     * Call a route the generated surface does not cover. `$path` is relative to `/api/v1`.
     * A write gets an `Idempotency-Key` and the approval loop like any operation.
     *
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'  $method
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<mixed>|PendingApprovalResult<ApiResponse<mixed>> : ApiResponse<mixed>)
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        $op = new OperationSpec(null, null, $method, '/'.ltrim($path, '/'), approval: true, body: $body !== null);
        $url = $this->baseUrl.$op->path.self::query($query);
        $finish = fn (Response $response, ?string $key): ApiResponse => $this->result($response, $key, $op->name(), Value::mixed(...));

        if ($options instanceof ReturnPendingApproval) {
            return $this->deferring($op, $url, $body, $options, $finish);
        }

        return $this->waiting($op, $url, $body, $options, $finish);
    }

    /**
     * `Retry-After` in milliseconds: delta-seconds or an HTTP date. Null when absent or
     * unparseable.
     */
    public static function retryAfterMs(Response $response): ?int
    {
        $raw = trim($response->header('Retry-After'));

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw * 1000;
        }

        $time = strtotime($raw);

        return $time === false ? null : max(0, $time * 1000 - Carbon::now()->getTimestampMs());
    }

    // ── The request lifecycle ────────────────────────────────────────────────────────

    /**
     * @template TResult
     *
     * @param  array<string, mixed>|null  $payload
     * @param  Closure(Response, ?string): TResult  $finish
     * @return TResult
     */
    private function waiting(OperationSpec $op, string $url, ?array $payload, ?CallOptions $options, Closure $finish): mixed
    {
        $key = $op->isWrite() ? ($options->idempotencyKey ?? self::uuid()) : null;
        $headers = $options === null ? [] : $options->headers;
        $response = $this->send($op->method, $url, $payload, $key, $options?->approvalId, $headers);
        $held = $this->heldApproval($response);

        if ($held !== null) {
            $response = $this->approveAndRepeat($op, $url, $payload, $key, $headers, $held, true);
        }

        return $finish($response, $key);
    }

    /**
     * @template TResult
     *
     * @param  array<string, mixed>|null  $payload
     * @param  Closure(Response, ?string): TResult  $finish
     * @return TResult|PendingApprovalResult<TResult>
     */
    private function deferring(OperationSpec $op, string $url, ?array $payload, CallOptions $options, Closure $finish): mixed
    {
        $key = $op->isWrite() ? ($options->idempotencyKey ?? self::uuid()) : null;
        $response = $this->send($op->method, $url, $payload, $key, $options->approvalId, $options->headers);
        $held = $this->heldApproval($response);

        if ($held === null) {
            return $finish($response, $key);
        }

        return new PendingApprovalResult(
            $held[0],
            $key,
            $op->action,
            fn (): mixed => $finish($this->approveAndRepeat($op, $url, $payload, $key, $options->headers, $held, false), $key),
        );
    }

    /**
     * Send with retries. Every attempt carries the SAME `Idempotency-Key`.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $url, ?array $payload, ?string $key, ?string $approvalId, array $headers): Response
    {
        $maxRetries = max(0, $this->options->maxRetries);
        $maxDelay = max(0, $this->options->maxDelayMs);

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->exchange($method, $url, $payload, $key, $approvalId, $headers);
            } catch (ConnectionException|TransferException $e) {
                if ($attempt >= $maxRetries) {
                    $path = parse_url($url, PHP_URL_PATH);

                    throw new ManagementNetworkException(
                        "{$method} ".(is_string($path) ? $path : $url)." failed: {$e->getMessage()}",
                        $key,
                        $e,
                    );
                }

                $this->sleep($this->backoff($attempt));

                continue;
            }

            if ($attempt >= $maxRetries || ! $this->shouldRetry($response)) {
                return $response;
            }

            $delay = self::retryAfterMs($response) ?? $this->backoff($attempt);

            if ($delay > $maxDelay) {
                return $response;
            }

            $this->sleep($delay);
        }
    }

    /**
     * One HTTP exchange.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     */
    private function exchange(string $method, string $url, ?array $payload, ?string $key, ?string $approvalId, array $headers): Response
    {
        $request = $this->http()
            ->withHeaders($this->headers($payload !== null, $key, $approvalId, $headers))
            ->withOptions(['timeout' => $this->options->timeout])
            ->withoutRedirecting();

        if ($payload !== null) {
            $request = $request->withBody(self::encode($payload), 'application/json');
        }

        return $request->send($method, $url);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function headers(bool $hasBody, ?string $key, ?string $approvalId, array $extra): array
    {
        $own = ['Accept' => 'application/json'];

        if ($hasBody) {
            $own['Content-Type'] = 'application/json';
        }

        if ($key !== null) {
            $own['Idempotency-Key'] = $key;
        }

        if ($approvalId !== null) {
            $own['Cbox-Approval'] = $approvalId;
        }

        if ($this->options->environment !== null) {
            $own['Cbox-Environment'] = $this->options->environment;
        }

        $own['Authorization'] = 'Bearer '.$this->credential();

        $headers = [];

        // Caller headers first, minus any that would shadow one of ours.
        foreach ([...$this->options->headers, ...$extra] as $name => $value) {
            foreach (array_keys($own) as $reserved) {
                if (strcasecmp($name, $reserved) === 0) {
                    continue 2;
                }
            }

            $headers[$name] = $value;
        }

        return [...$headers, ...$own];
    }

    private function credential(): string
    {
        if ($this->options->apiKey !== null) {
            return $this->options->apiKey;
        }

        $source = $this->options->accessToken;
        $token = $source instanceof Closure ? $source() : $source;

        if (! is_string($token) || $token === '') {
            throw ClientConfigurationException::because('The management client `accessToken` provider returned no token.');
        }

        return $token;
    }

    private function http(): Factory
    {
        if ($this->options->http !== null) {
            return $this->options->http;
        }

        // Resolved per request, so an `Http::fake()` made after this client was built
        // still reaches it.
        return Container::getInstance()->make(Factory::class);
    }

    private function shouldRetry(Response $response): bool
    {
        if ($response->status() >= 500 || $response->status() === 429) {
            return true;
        }

        if ($response->status() === 409) {
            // The first request with this key is still running: its answer is coming.
            $body = self::json($response->body());

            return is_array($body) && ($body['error'] ?? null) === 'idempotency_in_progress';
        }

        return false;
    }

    private function backoff(int $attempt): int
    {
        $exponential = max(0, $this->options->baseDelayMs) * (2 ** min($attempt, 20));
        $half = $exponential / 2;

        return (int) min(max(0, $this->options->maxDelayMs), $half + $half * (random_int(0, 1000) / 1000));
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            Sleep::usleep($milliseconds * 1000);
        }
    }

    // ── Approvals ────────────────────────────────────────────────────────────────────

    /**
     * The approval a `202 approval_required` carries, with the `Retry-After` it came with.
     *
     * @return array{0: PendingApproval, 1: int|null}|null
     */
    private function heldApproval(Response $response): ?array
    {
        if ($response->status() !== 202) {
            return null;
        }

        $body = self::json($response->body());

        if (! is_array($body) || ($body['error'] ?? null) !== 'approval_required' || ! is_array($body['approval'] ?? null)) {
            return null;
        }

        $approval = $body['approval'];
        $id = $approval['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $string = static fn (string $field, string $default): string => is_string($approval[$field] ?? null) ? $approval[$field] : $default;

        return [
            new PendingApproval(
                $id,
                $string('status', 'pending'),
                $string('binding_code', ''),
                $string('expires_at', ''),
                $string('poll_url', $this->baseUrl.$this->plane->approvalMount().'/action-approvals/'.rawurlencode($id)),
            ),
            self::retryAfterMs($response),
        ];
    }

    /**
     * Wait for the approval, repeat the request with it, and do that again if the repeat is
     * held too — up to {@see self::MAX_APPROVAL_ROUNDS} times.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     * @param  array{0: PendingApproval, 1: int|null}  $held
     */
    private function approveAndRepeat(OperationSpec $op, string $url, ?array $payload, ?string $key, array $headers, array $held, bool $notify): Response
    {
        $context = new ApprovalContext($op->action, $op->danger, $op->method, $op->path);

        for ($round = 1; ; $round++) {
            if (($notify || $round > 1) && $this->options->onApprovalRequired !== null) {
                ($this->options->onApprovalRequired)($held[0], $context);
            }

            $this->awaitApproval($held[0], $held[1]);

            $response = $this->send($op->method, $url, $payload, $key, $held[0]->id, $headers);
            $again = $this->heldApproval($response);

            if ($again === null) {
                return $response;
            }

            if ($round >= self::MAX_APPROVAL_ROUNDS) {
                throw new ApprovalException("The request was held for approval {$round} times; giving up.", 'consumed', $again[0]);
            }

            $held = $again;
        }
    }

    /** Poll an approval until the person decides. Returns on `approved`; throws otherwise. */
    private function awaitApproval(PendingApproval $approval, ?int $initialDelay): void
    {
        $pollUrl = $this->resolve($approval->pollUrl);

        if (self::originOf($pollUrl) !== $this->origin) {
            // The poll carries the same credential as the request. Never hand it to another host.
            throw new UnexpectedResponse("Refusing to poll approval {$approval->id} at another origin (".self::originOf($pollUrl).').');
        }

        $fallback = max(0, $this->options->approvalPollIntervalMs);
        $expires = $approval->expiresAt !== '' ? strtotime($approval->expiresAt) : false;
        $delay = $initialDelay ?? $fallback;

        for (; ;) {
            $this->sleep(min($delay, 60_000));

            $response = $this->send('GET', $pollUrl, null, null, null, []);

            if (! $response->successful()) {
                throw $this->error($response);
            }

            $body = self::json($response->body());
            $data = is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : [];
            $status = is_string($data['status'] ?? null) ? $data['status'] : 'pending';

            if ($status === 'approved') {
                return;
            }

            match ($status) {
                'denied' => throw ApprovalDenied::of($approval),
                'expired' => throw ApprovalExpired::of($approval),
                'consumed' => throw new ApprovalException("Approval {$approval->id} was already used by another request.", 'consumed', $approval),
                default => null,
            };

            if ($expires !== false && Carbon::now()->getTimestampMs() > $expires * 1000 + 30_000) {
                throw ApprovalExpired::of($approval);
            }

            $delay = self::retryAfterMs($response) ?? $fallback;
        }
    }

    // ── Results and errors ───────────────────────────────────────────────────────────

    /**
     * @template T
     *
     * @param  Closure(mixed, string): T  $decode
     * @return ApiResponse<T>
     */
    private function result(Response $response, ?string $key, string $where, Closure $decode): ApiResponse
    {
        if (! $response->successful()) {
            throw $this->error($response);
        }

        $body = $response->status() === 204 ? null : self::json($response->body());
        $envelope = is_array($body) && ! array_is_list($body) && array_key_exists('data', $body);
        $meta = $envelope && is_array($body['meta'] ?? null) ? Value::object($body['meta'], 'meta') : [];

        try {
            $data = $decode($envelope ? $body['data'] : $body, $where);
        } catch (UnexpectedResponse $e) {
            $e->body = $body;
            $e->idempotencyKey = $key;

            throw $e;
        }

        $requestId = $response->header('X-Request-Id');

        return new ApiResponse(
            $data,
            $response->status(),
            $meta,
            $body,
            strtolower($response->header('Idempotent-Replayed')) === 'true',
            $key,
            $requestId !== '' ? $requestId : null,
            self::headersOf($response),
        );
    }

    /**
     * @template T
     *
     * @param  ApiResponse<list<T>>  $response
     * @return Page<T>
     */
    private function toPage(ApiResponse $response): Page
    {
        $meta = $response->meta;

        return new Page(
            $response->data,
            ($meta['has_more'] ?? false) === true,
            is_string($meta['next_cursor'] ?? null) ? $meta['next_cursor'] : null,
            is_int($meta['next_page'] ?? null) ? $meta['next_page'] : null,
            is_int($meta['total'] ?? null) ? $meta['total'] : null,
            $response,
        );
    }

    private function error(Response $response): CboxIdApiException
    {
        $body = self::json($response->body());
        $fields = is_array($body) ? $body : [];
        $status = $response->status();
        $code = is_string($fields['error'] ?? null) ? $fields['error'] : "http_{$status}";

        // The management envelope says `message`; a bearer challenge (RFC 6750) says `error_description`.
        $message = match (true) {
            is_string($fields['message'] ?? null) => $fields['message'],
            is_string($fields['error_description'] ?? null) => $fields['error_description'],
            default => "HTTP {$status}",
        };

        $errors = [];

        if (is_array($fields['errors'] ?? null)) {
            foreach ($fields['errors'] as $field => $messages) {
                $errors[(string) $field] = is_array($messages)
                    ? array_values(array_map(static fn (mixed $m): string => is_scalar($m) ? (string) $m : '', $messages))
                    : [is_scalar($messages) ? (string) $messages : ''];
            }
        }

        // The envelope's `request_id` first: a proxy in between may rewrite or drop the header.
        $requestId = is_string($fields['request_id'] ?? null) && $fields['request_id'] !== ''
            ? $fields['request_id']
            : ($response->header('X-Request-Id') !== '' ? $response->header('X-Request-Id') : null);

        $retryAfter = self::retryAfterMs($response);

        return new CboxIdApiException(
            $status,
            $code,
            $message,
            $errors,
            $requestId,
            $retryAfter === null ? null : (int) ceil($retryAfter / 1000),
        );
    }

    // ── URLs, encoding, configuration ────────────────────────────────────────────────

    /**
     * @param  list<string>  $pathArgs
     * @param  array<string, mixed>  $query
     */
    private function url(OperationSpec $op, array $pathArgs, array $query): string
    {
        $index = 0;

        $path = preg_replace_callback('/\{([^}]+)\}/', static function (array $match) use (&$index, $pathArgs, $op): string {
            $value = $pathArgs[$index++] ?? '';

            if ($value === '') {
                throw new InvalidArgumentException("{$op->name()}: missing path parameter `{$match[1]}`.");
            }

            return rawurlencode($value);
        }, $op->path);

        return $this->baseUrl.$path.self::query($query);
    }

    /**
     * The query string, `?`-prefixed (or empty): lists as `key[]=…`, booleans as `1`/`0`
     * (Laravel's `boolean` rule does not take `true`), objects as JSON, nulls left out.
     *
     * @param  array<string, mixed>  $query
     */
    private static function query(array $query): string
    {
        $pairs = [];

        $append = static function (string $key, mixed $value) use (&$append, &$pairs): void {
            if ($value === null) {
                return;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    $append($key.'[]', $item);
                }

                return;
            }

            $string = match (true) {
                is_bool($value) => $value ? '1' : '0',
                is_array($value), is_object($value) => self::encode($value),
                is_scalar($value) => (string) $value,
                default => '',
            };

            $pairs[] = rawurlencode($key).'='.rawurlencode($string);
        };

        foreach ($query as $key => $value) {
            $append($key, $value);
        }

        return $pairs === [] ? '' : '?'.implode('&', $pairs);
    }

    /** A poll URL, which the server may give relative to the host. */
    private function resolve(string $url): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            return $url;
        }

        return str_starts_with($url, '/') ? $this->origin.$url : $this->baseUrl.'/'.$url;
    }

    private static function originOf(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * The origin, and the base URL ending in `/api/v1`. Every request carries a management
     * credential, so anything but https is refused — except on loopback, for development.
     *
     * @return array{0: string, 1: string}
     */
    private static function normalizeBaseUrl(string $raw): array
    {
        $parts = parse_url($raw);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw ClientConfigurationException::because('Management client `baseUrl` is not a valid URL.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $loopback = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);

        if ($scheme !== 'https' && ! ($scheme === 'http' && $loopback)) {
            throw ClientConfigurationException::because("Management client `baseUrl` must be https (got {$scheme}://{$host}).");
        }

        $origin = $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = rtrim($parts['path'] ?? '', '/');

        return [$origin, $origin.(str_ends_with($path, '/api/v1') ? $path : $path.'/api/v1')];
    }

    private function assertCredentials(Plane $plane, ClientOptions $options): void
    {
        $hasKey = $options->apiKey !== null;

        if ($hasKey === ($options->accessToken !== null)) {
            throw ClientConfigurationException::because('Pass exactly one of `apiKey` and `accessToken` to a management client.');
        }

        if ($hasKey) {
            $key = (string) $options->apiKey;
            $expected = $plane->keyPrefix();

            if ($key === '') {
                throw ClientConfigurationException::because('Management client `apiKey` is empty.');
            }

            if ($expected === null) {
                throw ClientConfigurationException::because("The {$plane->value} plane accepts no management key — only an access token a person delegated (`accessToken`).");
            }

            foreach (Plane::cases() as $other) {
                $prefix = $other->keyPrefix();

                if ($prefix !== null && $prefix !== $expected && str_starts_with($key, $prefix)) {
                    // Credentials never cross planes: the server would answer 401 to every request.
                    throw ClientConfigurationException::because("A `{$prefix}…` key cannot call the {$plane->value} plane; it takes `{$expected}…` keys.");
                }
            }
        }

        if ($options->environment !== null) {
            if ($plane !== Plane::Environment) {
                throw ClientConfigurationException::because("`environment` applies to the environment plane, not the {$plane->value} plane.");
            }

            if ($options->environment === '') {
                throw ClientConfigurationException::because('Management client `environment` is empty.');
            }

            if ($hasKey) {
                throw ClientConfigurationException::because(
                    '`environment` names the environment for a root access token. A `cbid_env_…` key is bound to its own '.
                    "environment's host: use that host as `baseUrl` instead.",
                );
            }
        }
    }

    /** @return array<string, list<string>> */
    private static function headersOf(Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[(string) $name] = array_values(array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                is_array($values) ? $values : [$values],
            ));
        }

        return $headers;
    }

    private static function json(string $text): mixed
    {
        if ($text === '') {
            return null;
        }

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /** @param array<array-key, mixed>|object $value */
    private static function encode(array|object $value): string
    {
        // An empty PHP array is an empty JSON object here: every body is an object.
        return $value === [] ? '{}' : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** A random (version 4) UUID, for an `Idempotency-Key`. */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
