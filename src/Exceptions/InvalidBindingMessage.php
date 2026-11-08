<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** The `binding_message` is missing, too long or not displayable (`invalid_binding_message`). */
class InvalidBindingMessage extends GrantFailed {}
