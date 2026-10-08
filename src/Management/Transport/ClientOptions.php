<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Closure;
use Illuminate\Http\Client\Factory;

/**
 * How a management client authenticates, where it sends requests, and how it retries and
 * waits for approvals. Built for you from a client's named constructor arguments:
 *
 *     new EnvironmentClient(baseUrl: 'https://acme.cboxid.com', apiKey: $key);
 */
readonly class ClientOptions
{
    /**
     * @param  string|null  $baseUrl  where the plane is served: an environment's own host for the environment and
     *                                account planes, the platform root for the workspace and platform planes
     *                                (`/api/v1` is appended unless it is there)
     * @param  string|null  $apiKey  a management key: `cbid_env_…` (environment plane) or `cbid_ws_…` (workspace plane)
     * @param  string|(Closure(): string)|null  $accessToken  a delegated OAuth access token, or a closure returning one —
     *                                                        called before every request, so it can refresh
     * @param  string|null  $environment  environment plane, with an access token on the PLATFORM ROOT's host only: the
     *                                    environment to act in (id or slug), sent as `Cbox-Environment`
     * @param  (Closure(PendingApproval, ApprovalContext): void)|null  $onApprovalRequired  called when a request is held
     *                                                                                      for a person's approval, before polling starts
     * @param  int  $approvalPollIntervalMs  how often to poll an approval when the server sends no `Retry-After`
     * @param  int  $maxRetries  retries after the first attempt, for network failures, 5xx, 429 and
     *                           `409 idempotency_in_progress`; 0 turns retrying off
     * @param  int  $baseDelayMs  the first backoff delay, doubled per attempt, with jitter
     * @param  int  $maxDelayMs  the longest one wait may be; a longer `Retry-After` is thrown, not waited out
     * @param  float  $timeout  seconds per attempt
     * @param  array<string, string>  $headers  sent on every request (e.g. a `User-Agent`); cannot override `Authorization`
     * @param  Factory|null  $http  the HTTP client factory; default the container's, so `Http::fake()` reaches it
     */
    public function __construct(
        public ?string $baseUrl = null,
        #[\SensitiveParameter]
        public ?string $apiKey = null,
        #[\SensitiveParameter]
        public string|Closure|null $accessToken = null,
        public ?string $environment = null,
        public ?Closure $onApprovalRequired = null,
        public int $approvalPollIntervalMs = 2000,
        public int $maxRetries = 3,
        public int $baseDelayMs = 500,
        public int $maxDelayMs = 30000,
        public float $timeout = 30,
        public array $headers = [],
        public ?Factory $http = null,
    ) {}
}
