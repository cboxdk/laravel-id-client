<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/**
 * Per-call options every generated method takes last.
 *
 *     $env->apps->update($id, ['name' => 'Billing'], new CallOptions(idempotencyKey: $key));
 *
 * A write held for a person's approval is waited on by default. To get the pending
 * approval back instead, pass {@see self::returnPendingApproval()}.
 */
readonly class CallOptions
{
    /**
     * @param  string|null  $idempotencyKey  your own `Idempotency-Key` for a write; default a fresh UUID per call, reused on its retries
     * @param  string|null  $approvalId  send `Cbox-Approval: <id>` yourself — for an approval you obtained and waited on elsewhere
     * @param  array<string, string>  $headers  extra request headers; cannot override `Authorization`
     */
    public function __construct(
        public ?string $idempotencyKey = null,
        public ?string $approvalId = null,
        public array $headers = [],
    ) {}

    /**
     * On `202 approval_required`, return a {@see PendingApprovalResult} rather than waiting:
     * show its binding code, then `resume()` it when you are ready to wait.
     *
     * @param  array<string, string>  $headers
     */
    public static function returnPendingApproval(?string $idempotencyKey = null, ?string $approvalId = null, array $headers = []): ReturnPendingApproval
    {
        return new ReturnPendingApproval($idempotencyKey, $approvalId, $headers);
    }
}
