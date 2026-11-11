<?php

declare(strict_types=1);

namespace Cbox\Id\Client\SignIn;

use Illuminate\Contracts\Session\Session;

/**
 * Stops a sign-in that keeps coming back without signing anyone in.
 *
 * With a live Cbox ID session a round trip shows nothing, so a callback that fails and
 * returns to `/login` (or an app that refuses the person after every sign-in) would
 * bounce between the two origins until the browser gives up. Every time a callback asks
 * the SDK to finish a sign-in is noted here; `/login` reads the count before it starts
 * another, and shows the error page instead once there were too many within the window.
 */
final class SignInRoundTrips
{
    public const KEY = 'cbox-id-client.sign-in.returns';

    private const WINDOW = 60;

    public static function note(Session $session): void
    {
        $session->put(self::KEY, [...self::recent($session), time()]);
    }

    public static function tooMany(Session $session): bool
    {
        $limit = config('cbox-id-client.sign_in.max_round_trips', 3);

        return count(self::recent($session)) >= (is_numeric($limit) ? max(1, (int) $limit) : 3);
    }

    public static function forget(Session $session): void
    {
        $session->forget(self::KEY);
    }

    /** @return list<int> */
    private static function recent(Session $session): array
    {
        $returns = $session->get(self::KEY);
        $since = time() - self::WINDOW;

        return is_array($returns)
            ? array_values(array_filter($returns, static fn (mixed $at): bool => is_int($at) && $at > $since))
            : [];
    }
}
