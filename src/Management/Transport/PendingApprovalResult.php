<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Exceptions\ApprovalDenied;
use Cbox\Id\Client\Exceptions\ApprovalExpired;
use Closure;

/**
 * A call held for a person's approval, returned (instead of waited on) because it was
 * made with {@see CallOptions::returnPendingApproval()}.
 *
 *     $outcome = $env->apps->secrets->rotate($id, [], CallOptions::returnPendingApproval());
 *
 *     if ($outcome instanceof PendingApprovalResult) {
 *         echo "Approve on your device. Code: {$outcome->approval->bindingCode}";
 *         $outcome = $outcome->resume();   // polls, then repeats the request
 *     }
 *
 * `resume()` repeats the request with `Cbox-Approval` and the SAME `Idempotency-Key`, so
 * it lands once however often it is resumed.
 *
 * @template TResult
 */
readonly class PendingApprovalResult
{
    /**
     * @param  Closure(): TResult  $resume
     */
    public function __construct(
        public PendingApproval $approval,
        public ?string $idempotencyKey,
        /** The action that was held, e.g. `apps.secrets.rotate`. */
        public ?string $action,
        private Closure $resume,
    ) {}

    /**
     * Poll until the person decides, then repeat the request with the approval.
     *
     * @return TResult
     *
     * @throws ApprovalDenied
     * @throws ApprovalExpired
     */
    public function resume(): mixed
    {
        return ($this->resume)();
    }
}
