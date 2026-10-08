<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Management\Platform\Resources\ActionApprovals;
use Cbox\Id\Client\Management\Platform\Resources\Environments;
use Cbox\Id\Client\Management\Platform\Resources\Operators;
use Cbox\Id\Client\Management\Platform\Resources\Organizations;
use Cbox\Id\Client\Management\Platform\Resources\Workspaces;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\Plane;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * Cbox ID — Operator API.
 *
 * The **deployment itself**, for the staff who run it: stand up customer workspaces,
 * create and bootstrap environments, manage organizations inside any environment, and
 * keep the operator roster. Everything the console's Platform pages do, as the same
 * actions — the same rules, refusals and audit trail.
 *
 * `baseUrl` defaults to `https://api.cboxid.com`.
 *
 * GENERATED from `openapi/platform.yaml` by `composer generate`.
 */
class PlatformClient extends ManagementClient
{
    public readonly ActionApprovals $actionApprovals;

    public readonly Environments $environments;

    public readonly Operators $operators;

    public readonly Organizations $organizations;

    public readonly Workspaces $workspaces;

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
        $this->operators = new Operators($this->transport);
        $this->organizations = new Organizations($this->transport);
        $this->workspaces = new Workspaces($this->transport);
    }

    public static function plane(): Plane
    {
        return Plane::Platform;
    }

    public static function defaultBaseUrl(): ?string
    {
        return 'https://api.cboxid.com';
    }
}
