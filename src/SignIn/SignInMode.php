<?php

declare(strict_types=1);

namespace Cbox\Id\Client\SignIn;

/**
 * Where a person types their credentials (`cbox-id-client.sign_in.mode`).
 *
 * `Embedded` is the app's own sign-in page: the app draws the form and the SDK does
 * nothing on `/login`. `Redirect` has no page in the app at all: `/login`, `/signup` and
 * the other entry points answer with a redirect to Cbox ID's hosted sign-in, and
 * passwords and passkeys never reach the app's origin.
 */
enum SignInMode: string
{
    case Embedded = 'embedded';
    case Redirect = 'redirect';

    /** The configured mode. Anything that is not `redirect` is `embedded`. */
    public static function configured(): self
    {
        $mode = config('cbox-id-client.sign_in.mode');

        return is_string($mode) && strtolower(trim($mode)) === self::Redirect->value ? self::Redirect : self::Embedded;
    }
}
