<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Management\Account\Resources\ActionApprovals;
use Cbox\Id\Client\Management\Account\Resources\ApiKeys;
use Cbox\Id\Client\Management\Account\Resources\Applications;
use Cbox\Id\Client\Management\Account\Resources\Devices;
use Cbox\Id\Client\Management\Account\Resources\Mfa;
use Cbox\Id\Client\Management\Account\Resources\Organizations;
use Cbox\Id\Client\Management\Account\Resources\Passkeys;
use Cbox\Id\Client\Management\Account\Resources\Pipes;
use Cbox\Id\Client\Management\Account\Resources\Profile;
use Cbox\Id\Client\Management\Account\Resources\Sessions;
use Cbox\Id\Client\Management\Account\Resources\Social;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\Plane;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * Cbox ID — My Account API.
 *
 * **Your own account** — the person calling, and nobody else: your profile, where you are
 * signed in, which applications may act as you, your own API keys for the apps built on
 * this environment, your sign-in methods, your trusted devices and the organizations you
 * belong to. The same actions the
 * console's My account pages run, with the same rules, refusals and audit trail.
 *
 * `baseUrl` is required: the environment's own host.
 *
 * GENERATED from `openapi/account.yaml` by `composer generate`.
 */
class AccountClient extends ManagementClient
{
    public readonly ActionApprovals $actionApprovals;

    public readonly ApiKeys $apiKeys;

    public readonly Applications $applications;

    public readonly Devices $devices;

    public readonly Mfa $mfa;

    public readonly Organizations $organizations;

    public readonly Passkeys $passkeys;

    public readonly Pipes $pipes;

    public readonly Profile $profile;

    public readonly Sessions $sessions;

    public readonly Social $social;

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
        $this->apiKeys = new ApiKeys($this->transport);
        $this->applications = new Applications($this->transport);
        $this->devices = new Devices($this->transport);
        $this->mfa = new Mfa($this->transport);
        $this->organizations = new Organizations($this->transport);
        $this->passkeys = new Passkeys($this->transport);
        $this->pipes = new Pipes($this->transport);
        $this->profile = new Profile($this->transport);
        $this->sessions = new Sessions($this->transport);
        $this->social = new Social($this->transport);
    }

    public static function plane(): Plane
    {
        return Plane::Account;
    }
}
