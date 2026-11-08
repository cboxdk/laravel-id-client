<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The `resource` (RFC 8707) is missing or not one this client may use (`invalid_target`) —
 * including a public client asking for CIBA on a resource that does not allow it.
 */
class InvalidTarget extends GrantFailed {}
