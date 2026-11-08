<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** The `login_hint` matches no person Cbox ID can send the request to (`unknown_user_id`). */
class UnknownUser extends GrantFailed {}
