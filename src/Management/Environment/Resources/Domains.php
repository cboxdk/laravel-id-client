<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\CustomDomain;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `domains.*` on the environment plane. */
class Domains
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Start serving this environment on a custom domain: returns the DNS TXT record that proves you control it.
     *
     * `POST /domains` · action `domains.add` · scope `domains:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{domain: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<CustomDomain>|PendingApprovalResult<ApiResponse<CustomDomain>> : ApiResponse<CustomDomain>)
     */
    public function add(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('domains.add'), [], $body, $options, Value::dto(CustomDomain::fromArray(...)));
    }

    /**
     * Read this environment's custom domain, and the DNS TXT record that proves a pending one.
     *
     * `GET /domains` · action `domains.get` · scope `domains:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<CustomDomain>|PendingApprovalResult<ApiResponse<CustomDomain>> : ApiResponse<CustomDomain>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('domains.get'), [], [], $options, Value::dto(CustomDomain::fromArray(...)));
    }

    /**
     * Stop serving this environment on its custom domain. Anything using that domain stops reaching the environment.
     *
     * `DELETE /domains` · action `domains.remove` · scope `domains:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('domains.remove'), [], [], $options, Value::none(...));
    }

    /**
     * Look for the pending domain's DNS TXT record and, once it is visible, serve this environment on that domain.
     *
     * `POST /domains/verify` · action `domains.verify` · scope `domains:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<CustomDomain>|PendingApprovalResult<ApiResponse<CustomDomain>> : ApiResponse<CustomDomain>)
     */
    public function verify(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('domains.verify'), [], [], $options, Value::dto(CustomDomain::fromArray(...)));
    }
}
