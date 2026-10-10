<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The provider stopped accepting the connection — revoked, or the refresh token expired (409
 * `reauthorization_required`). Send the person to `$connectUrl` to connect again.
 */
class PipeReauthorizationRequired extends PipeLeaseFailed {}
