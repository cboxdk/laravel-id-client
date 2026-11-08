<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** The person declined the request (`access_denied`). Do not retry it unprompted. */
class AuthorizationDenied extends GrantFailed {}
