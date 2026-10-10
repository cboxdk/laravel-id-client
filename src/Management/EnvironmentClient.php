<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management;

use Cbox\Id\Client\Management\Environment\Resources\AccessReviews;
use Cbox\Id\Client\Management\Environment\Resources\ActionApprovals;
use Cbox\Id\Client\Management\Environment\Resources\ApiKeys;
use Cbox\Id\Client\Management\Environment\Resources\Apis;
use Cbox\Id\Client\Management\Environment\Resources\Approvals;
use Cbox\Id\Client\Management\Environment\Resources\Apps;
use Cbox\Id\Client\Management\Environment\Resources\Audit;
use Cbox\Id\Client\Management\Environment\Resources\AuditLogs;
use Cbox\Id\Client\Management\Environment\Resources\Branding;
use Cbox\Id\Client\Management\Environment\Resources\Directories;
use Cbox\Id\Client\Management\Environment\Resources\Domains;
use Cbox\Id\Client\Management\Environment\Resources\Events;
use Cbox\Id\Client\Management\Environment\Resources\FeatureFlags;
use Cbox\Id\Client\Management\Environment\Resources\Fga;
use Cbox\Id\Client\Management\Environment\Resources\FrontendKeys;
use Cbox\Id\Client\Management\Environment\Resources\Hooks;
use Cbox\Id\Client\Management\Environment\Resources\Invitations;
use Cbox\Id\Client\Management\Environment\Resources\Keys;
use Cbox\Id\Client\Management\Environment\Resources\LegacyLogin;
use Cbox\Id\Client\Management\Environment\Resources\LogStreams;
use Cbox\Id\Client\Management\Environment\Resources\Members;
use Cbox\Id\Client\Management\Environment\Resources\Organizations;
use Cbox\Id\Client\Management\Environment\Resources\Permissions;
use Cbox\Id\Client\Management\Environment\Resources\Pipes;
use Cbox\Id\Client\Management\Environment\Resources\Provisioning;
use Cbox\Id\Client\Management\Environment\Resources\Radar;
use Cbox\Id\Client\Management\Environment\Resources\Roles;
use Cbox\Id\Client\Management\Environment\Resources\SamlApps;
use Cbox\Id\Client\Management\Environment\Resources\Signin;
use Cbox\Id\Client\Management\Environment\Resources\SodPolicies;
use Cbox\Id\Client\Management\Environment\Resources\Sso;
use Cbox\Id\Client\Management\Environment\Resources\SupportSessions;
use Cbox\Id\Client\Management\Environment\Resources\TokenVault;
use Cbox\Id\Client\Management\Environment\Resources\Users;
use Cbox\Id\Client\Management\Environment\Resources\Webhooks;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\Plane;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * Cbox ID — Environment Management API.
 *
 * The **per-environment management plane** — the API an app's own backend uses to run
 * its tenancy inside ONE environment: organizations and their owners, members,
 * invitations, roles (in one organization, or everywhere for your staff), apps, APIs,
 * the API keys customers made for them, and support sessions. It is served on
 * the environment's own host ({slug}.cboxid.com or a custom domain): the host pins
 * the environment, and the request is authenticated with an environment API key
 * (`cbid_env_…`).
 *
 * `baseUrl` is required: the environment's own host.
 *
 * GENERATED from `openapi/environment.yaml` by `composer generate`.
 */
class EnvironmentClient extends ManagementClient
{
    public readonly AccessReviews $accessReviews;

    public readonly ActionApprovals $actionApprovals;

    public readonly ApiKeys $apiKeys;

    public readonly Apis $apis;

    public readonly Approvals $approvals;

    public readonly Apps $apps;

    public readonly Audit $audit;

    public readonly AuditLogs $auditLogs;

    public readonly Branding $branding;

    public readonly Directories $directories;

    public readonly Domains $domains;

    public readonly Events $events;

    public readonly FeatureFlags $featureFlags;

    public readonly Fga $fga;

    public readonly FrontendKeys $frontendKeys;

    public readonly Hooks $hooks;

    public readonly Invitations $invitations;

    public readonly Keys $keys;

    public readonly LegacyLogin $legacyLogin;

    public readonly LogStreams $logStreams;

    public readonly Members $members;

    public readonly Organizations $organizations;

    public readonly Permissions $permissions;

    public readonly Pipes $pipes;

    public readonly Provisioning $provisioning;

    public readonly Radar $radar;

    public readonly Roles $roles;

    public readonly SamlApps $samlApps;

    public readonly Signin $signin;

    public readonly SodPolicies $sodPolicies;

    public readonly Sso $sso;

    public readonly SupportSessions $supportSessions;

    public readonly TokenVault $tokenVault;

    public readonly Users $users;

    public readonly Webhooks $webhooks;

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
        $this->accessReviews = new AccessReviews($this->transport);
        $this->actionApprovals = new ActionApprovals($this->transport);
        $this->apiKeys = new ApiKeys($this->transport);
        $this->apis = new Apis($this->transport);
        $this->approvals = new Approvals($this->transport);
        $this->apps = new Apps($this->transport);
        $this->audit = new Audit($this->transport);
        $this->auditLogs = new AuditLogs($this->transport);
        $this->branding = new Branding($this->transport);
        $this->directories = new Directories($this->transport);
        $this->domains = new Domains($this->transport);
        $this->events = new Events($this->transport);
        $this->featureFlags = new FeatureFlags($this->transport);
        $this->fga = new Fga($this->transport);
        $this->frontendKeys = new FrontendKeys($this->transport);
        $this->hooks = new Hooks($this->transport);
        $this->invitations = new Invitations($this->transport);
        $this->keys = new Keys($this->transport);
        $this->legacyLogin = new LegacyLogin($this->transport);
        $this->logStreams = new LogStreams($this->transport);
        $this->members = new Members($this->transport);
        $this->organizations = new Organizations($this->transport);
        $this->permissions = new Permissions($this->transport);
        $this->pipes = new Pipes($this->transport);
        $this->provisioning = new Provisioning($this->transport);
        $this->radar = new Radar($this->transport);
        $this->roles = new Roles($this->transport);
        $this->samlApps = new SamlApps($this->transport);
        $this->signin = new Signin($this->transport);
        $this->sodPolicies = new SodPolicies($this->transport);
        $this->sso = new Sso($this->transport);
        $this->supportSessions = new SupportSessions($this->transport);
        $this->tokenVault = new TokenVault($this->transport);
        $this->users = new Users($this->transport);
        $this->webhooks = new Webhooks($this->transport);
    }

    public static function plane(): Plane
    {
        return Plane::Environment;
    }
}
