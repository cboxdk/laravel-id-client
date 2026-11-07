<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `user_property_namespaces.*` on the environment plane. */
class UserPropertyNamespaces
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create or replace the app and webhook grants for a user-property namespace. Empty grants revoke access without deleting user data.
     *
     * `PUT /user-property-namespaces/{name}` · action `user_property_namespaces.configure` · scope `user_property_namespaces:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{readers?: list<string>, writers?: list<string>, webhooks?: array<string, mixed>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function configure(string $name, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('user_property_namespaces.configure'), [$name], $body, $options, Value::object(...));
    }

    /**
     * Read the app and webhook grants for a user-property namespace.
     *
     * `GET /user-property-namespaces/{name}` · action `user_property_namespaces.show` · scope `user_property_namespaces:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function show(string $name, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('user_property_namespaces.show'), [$name], [], $options, Value::object(...));
    }
}
