<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** Cbox ID did not accept the client credentials (`invalid_client`): a wrong id, secret or signing key. */
class InvalidClient extends GrantFailed {}
