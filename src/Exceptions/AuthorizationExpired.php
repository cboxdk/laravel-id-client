<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** The request expired before the person answered (`expired_token`). Start a new one. */
class AuthorizationExpired extends GrantFailed {}
