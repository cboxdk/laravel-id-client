<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use RuntimeException;

/**
 * Everything this SDK throws on purpose.
 *
 * One type to catch at the edge of an integration, so a callback route can answer "Cbox
 * ID said no, or is not set up" without listing every refusal by name — and without
 * catching `RuntimeException`, which is what the apps built on 0.12 did and which also
 * swallows every unrelated bug in the same block.
 *
 * Still a `RuntimeException`, so code that caught that keeps working.
 */
abstract class CboxIdException extends RuntimeException {}
