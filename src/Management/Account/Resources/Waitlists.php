<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `waitlists.*` on the account plane. */
class Waitlists
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Join a published service waitlist with your verified email. Does not buy a plan or grant access.
     *
     * Requires scope `account:waitlists:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/services/{program}/waitlist` · action `account.waitlists.join` · scope `account:waitlists:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization?: string|null, interest?: 'notify'|'test'|null, country?: string|null, answers?: array<string, mixed>, reference: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function join(string $program, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.waitlists.join'), [$program], $body, $options, Value::object(...));
    }

    /**
     * Withdraw your own feature interest or beta application for a team. Active grants are managed by the service.
     *
     * Requires scope `account:waitlists:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/services/{program}/waitlist/leave` · action `account.waitlists.leave` · scope `account:waitlists:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization: string, reference: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function leave(string $program, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.waitlists.leave'), [$program], $body, $options, Value::object(...));
    }
}
