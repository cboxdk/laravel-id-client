<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ClientOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\Plane;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * What the four generated clients share: construction, the transport, and an escape hatch
 * for a route the generated surface does not cover. Each client adds its plane's
 * resources as properties — `$env->apps->secrets->rotate(…)`.
 *
 * Server-side code only: every client holds a management credential.
 */
abstract class ManagementClient
{
    /** The transport every method runs on — authentication, retries, approvals. */
    public readonly ManagementTransport $transport;

    /**
     * Exactly one of `$apiKey` and `$accessToken`.
     *
     * @param  string|null  $baseUrl  the plane's host; `/api/v1` is appended unless it is there
     * @param  string|null  $apiKey  a management key — `cbid_env_…` (environment) or `cbid_ws_…` (workspace)
     * @param  string|(Closure(): string)|null  $accessToken  a delegated access token, or a closure returning one before every request
     * @param  string|null  $environment  environment plane with a root access token: the environment (id or slug), sent as `Cbox-Environment`
     * @param  (Closure(PendingApproval, ApprovalContext): void)|null  $onApprovalRequired  told when a request is held for approval — show `$approval->bindingCode`
     * @param  array<string, string>  $headers
     */
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
        $this->transport = new ManagementTransport(static::plane(), new ClientOptions(
            $baseUrl,
            $apiKey,
            $accessToken,
            $environment,
            $onApprovalRequired,
            $approvalPollIntervalMs,
            $maxRetries,
            $baseDelayMs,
            $maxDelayMs,
            $timeout,
            $headers,
            $http,
        ), static::defaultBaseUrl());
    }

    /** The plane this client calls. */
    abstract public static function plane(): Plane;

    /** The host the plane is served at when no `baseUrl` is given; null when it has to be named. */
    public static function defaultBaseUrl(): ?string
    {
        return null;
    }

    /**
     * Call a route by method and path (relative to `/api/v1`) — for anything not generated.
     * A write gets an `Idempotency-Key` and the approval loop like any operation.
     *
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'  $method
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<mixed>|PendingApprovalResult<ApiResponse<mixed>> : ApiResponse<mixed>)
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->request($method, $path, $query, $body, $options);
    }
}
