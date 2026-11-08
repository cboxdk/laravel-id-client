<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The `auth_req_id` or `device_code` is unknown, already redeemed, or belongs to another
 * client (`invalid_grant`).
 */
class InvalidGrant extends GrantFailed {}
