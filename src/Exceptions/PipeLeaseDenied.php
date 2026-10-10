<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The lease was denied (403 `lease_denied`): the app is not granted the pipe, the pipe is disabled
 * or missing, the person is not in the app's organization, or `userId` names someone other
 * than the token's person. One answer for every reason, by design.
 */
class PipeLeaseDenied extends PipeLeaseFailed {}
