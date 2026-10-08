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

/** `apps.scopes.*` on the environment plane. */
class AppsScopes
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Replace an app's complete scope set — the ceiling of what it may request, applied from its next token.
     *
     * `PUT /apps/{id}/scopes` · action `apps.scopes.set` · scope `apps:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{scopes?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<App>|PendingApprovalResult<ApiResponse<App>> : ApiResponse<App>)
     */
    public function set(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.scopes.set'), [$id], $body, $options, Value::dto(App::fromArray(...)));
    }
}
