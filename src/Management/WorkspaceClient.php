<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\Plane;
use Cbox\Id\Client\Management\Workspace\Resources\ActionApprovals;
use Cbox\Id\Client\Management\Workspace\Resources\Environments;
use Cbox\Id\Client\Management\Workspace\Resources\Keys;
use Cbox\Id\Client\Management\Workspace\Resources\Projects;
use Cbox\Id\Client\Management\Workspace\Resources\Team;
use Cbox\Id\Client\Management\Workspace\Resources\Workspace;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * Cbox ID — Workspace Management API.
 *
 * The **global organization-management plane** — above every environment. Manage the
 * workspace, its **projects** (IdP products, each a billing anchor) and their
 * environments, its team, and its keys with a workspace key.
 *
 * `baseUrl` defaults to `https://api.cboxid.com`.
 *
 * GENERATED from `openapi/workspace.yaml` by `composer generate`.
 */
class WorkspaceClient extends ManagementClient
{
    public readonly ActionApprovals $actionApprovals;

    public readonly Environments $environments;

    public readonly Keys $keys;

    public readonly Projects $projects;

    public readonly Team $team;

    public readonly Workspace $workspace;

    /**
     * Exactly one of `$apiKey` and `$accessToken`. See {@see ManagementClient::__construct()}.
     *
     * @param  string|(Closure(): string)|null  $accessToken
     * @param  (Closure(PendingApproval, ApprovalContext): void)|null  $onApprovalRequired
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
        parent::__construct($baseUrl, $apiKey, $accessToken, $environment, $onApprovalRequired, $approvalPollIntervalMs, $maxRetries, $baseDelayMs, $maxDelayMs, $timeout, $headers, $http);
        $this->actionApprovals = new ActionApprovals($this->transport);
        $this->environments = new Environments($this->transport);
        $this->keys = new Keys($this->transport);
        $this->projects = new Projects($this->transport);
        $this->team = new Team($this->transport);
        $this->workspace = new Workspace($this->transport);
    }

    public static function plane(): Plane
    {
        return Plane::Workspace;
    }

    public static function defaultBaseUrl(): ?string
    {
        return 'https://api.cboxid.com';
    }
}
