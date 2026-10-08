<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Events\ManagementApprovalRequired;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Management\AuditLogs\AuditLogger;
use Cbox\Id\Client\Management\AuditLogs\AuditLogs;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The typed management clients, configured from `cbox-id-client.management` — what the
 * `CboxIdApi` facade and the container's `EnvironmentClient` / `WorkspaceClient` bindings
 * hand out.
 *
 * The key-authenticated clients are built once and shared; the token-authenticated ones
 * (a person's delegated token) are built per call, because the token is per person. Every
 * client dispatches {@see ManagementApprovalRequired} when a call is held for approval.
 */
class ManagementClients
{
    private ?EnvironmentClient $environment = null;

    private ?WorkspaceClient $workspace = null;

    private ?AuditLogger $auditLogger = null;

    /**
     * @param  array<string, mixed>  $config  `cbox-id-client.management`
     * @param  string  $issuer  `cbox-id-client.issuer`: the environment's own host, the default `url`
     */
    public function __construct(
        private readonly array $config,
        private readonly string $issuer,
        private readonly ?Dispatcher $events = null,
    ) {}

    /** The environment plane, with `CBOX_ID_MANAGEMENT_KEY` (a `cbid_env_…` key) on the environment's host. */
    public function environment(): EnvironmentClient
    {
        $key = $this->string('key');

        if ($key === '') {
            throw NotConfigured::key('management.key', 'call the environment management API');
        }

        return $this->environment ??= new EnvironmentClient(
            baseUrl: $this->environmentUrl(),
            apiKey: $key,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /** The workspace plane, with `CBOX_ID_WORKSPACE_KEY` (a `cbid_ws_…` key) on the platform root. */
    public function workspace(): WorkspaceClient
    {
        $key = $this->string('workspace_key');

        if ($key === '') {
            throw NotConfigured::key('management.workspace_key', 'call the workspace management API');
        }

        return $this->workspace ??= new WorkspaceClient(
            baseUrl: $this->rootUrl(),
            apiKey: $key,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /**
     * The environment plane as a person, with their delegated access token. With
     * `$environment` (id or slug) the calls go to the platform root and name the
     * environment in `Cbox-Environment`; without, to this environment's own host.
     *
     * @param  string|(Closure(): string)  $accessToken
     */
    public function environmentAs(string|Closure $accessToken, ?string $environment = null, ?string $baseUrl = null): EnvironmentClient
    {
        return new EnvironmentClient(
            baseUrl: $baseUrl ?? ($environment !== null ? $this->rootUrl() : $this->environmentUrl()),
            accessToken: $accessToken,
            environment: $environment,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /**
     * The workspace plane as a person, with their root access token.
     *
     * @param  string|(Closure(): string)  $accessToken
     */
    public function workspaceAs(string|Closure $accessToken, ?string $baseUrl = null): WorkspaceClient
    {
        return new WorkspaceClient(
            baseUrl: $baseUrl ?? $this->rootUrl(),
            accessToken: $accessToken,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /**
     * The platform plane, with an operator's delegated token.
     *
     * @param  string|(Closure(): string)  $accessToken
     */
    public function platform(string|Closure $accessToken, ?string $baseUrl = null): PlatformClient
    {
        return new PlatformClient(
            baseUrl: $baseUrl ?? $this->rootUrl(),
            accessToken: $accessToken,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /**
     * A person's own account, with their delegated token, on this environment's host.
     *
     * @param  string|(Closure(): string)  $accessToken
     */
    public function account(string|Closure $accessToken, ?string $baseUrl = null): AccountClient
    {
        return new AccountClient(
            baseUrl: $baseUrl ?? $this->issuerUrl(),
            accessToken: $accessToken,
            onApprovalRequired: $this->approvalListener(),
            approvalPollIntervalMs: $this->int('approval_poll_interval', 2000),
            maxRetries: $this->int('retries', 3),
            timeout: $this->int('timeout', 30),
        );
    }

    /** Audit Logs ergonomics on the environment client. */
    public function auditLogs(): AuditLogs
    {
        return new AuditLogs($this->environment());
    }

    /**
     * The shared, buffered audit logger. The service provider flushes it when the
     * application terminates; a queue worker or a long-running command should call
     * `flush()` itself.
     */
    public function auditLogger(): AuditLogger
    {
        return $this->auditLogger ??= new AuditLogger($this->environment());
    }

    /** Whether {@see self::auditLogger()} was used, so there may be something to flush. */
    public function hasAuditLogger(): bool
    {
        return $this->auditLogger !== null;
    }

    private function environmentUrl(): string
    {
        $url = $this->string('url');

        return $url !== '' ? $url : $this->issuerUrl();
    }

    private function issuerUrl(): string
    {
        if ($this->issuer === '') {
            throw NotConfigured::key('issuer', 'reach the environment management API');
        }

        return $this->issuer;
    }

    private function rootUrl(): string
    {
        $url = $this->string('root_url');

        return $url !== '' ? $url : 'https://api.cboxid.com';
    }

    /** @return Closure(PendingApproval, ApprovalContext): void */
    private function approvalListener(): Closure
    {
        return function (PendingApproval $approval, ApprovalContext $context): void {
            $this->events?->dispatch(new ManagementApprovalRequired($approval, $context));
        };
    }

    private function string(string $key): string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private function int(string $key, int $default): int
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
