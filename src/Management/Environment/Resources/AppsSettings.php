<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\App;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `apps.settings.*` on the environment plane. */
class AppsSettings
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Set the prefix of an app's customer API keys (acme_live), which lets its users create keys for it, or null to stop new keys.
     *
     * `PUT /apps/{id}/settings/api-key-prefix` · action `apps.settings.api_key_prefix` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{prefix?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function apiKeyPrefix(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.settings.api_key_prefix'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Set or clear the URI an app is sent a logout token at when somebody signs out (OIDC Back-Channel Logout).
     *
     * `PUT /apps/{id}/settings/backchannel-logout` · action `apps.settings.backchannel_logout` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{uri?: string|null, session_required?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function backchannelLogout(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.settings.backchannel_logout'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Turn token exchange (RFC 8693) on or off for a confidential app.
     *
     * `PUT /apps/{id}/settings/token-exchange` · action `apps.settings.token_exchange` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{enabled: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function tokenExchange(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.settings.token_exchange'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }

    /**
     * Set how long an app's access tokens live, in seconds, or null for the install's default.
     *
     * `PUT /apps/{id}/settings/token-lifetime` · action `apps.settings.token_lifetime` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{access_token_ttl?: int|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function tokenLifetime(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.settings.token_lifetime'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }
}
