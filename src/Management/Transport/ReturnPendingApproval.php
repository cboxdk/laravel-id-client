<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/**
 * Call options that ask for a held write to be RETURNED as a {@see PendingApprovalResult}
 * instead of waited on. A type of its own so a method's return type says which you get:
 * with these options, `ApiResponse<T>|PendingApprovalResult<ApiResponse<T>>`; without,
 * just `ApiResponse<T>`.
 */
readonly class ReturnPendingApproval extends CallOptions {}
