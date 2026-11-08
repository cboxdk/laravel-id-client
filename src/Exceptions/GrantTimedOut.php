<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * `waitForToken()` reached its maximum wait while the person had not answered yet.
 * The request itself may still be alive: poll it again, or let it expire.
 */
class GrantTimedOut extends GrantFailed {}
