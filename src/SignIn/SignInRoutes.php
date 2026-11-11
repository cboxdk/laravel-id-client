<?php

declare(strict_types=1);

namespace Cbox\Id\Client\SignIn;

use Cbox\Id\Client\SignIn\Http\SignInController;
use Illuminate\Support\Facades\Route;

/**
 * The sign-in entry points of an app that has no sign-in page of its own, from
 * `cbox-id-client.sign_in`. Registered only in redirect mode ({@see SignInMode}):
 *
 *  - GET `login_path` (`/login`)                    login
 *  - GET `signup_path` (`/signup`)                  signup
 *  - GET `launch_path` (`/login/launch`)            login.launch
 *  - GET `invitation_path` (`/login/invitation`)    login.invitation
 *  - GET `error_path` (`/login/error`)              login.error
 *  - GET `signed_out_path` (`/login/signed-out`)    login.signed-out
 *
 * The first four answer with a redirect to Cbox ID. The last two are the only pages:
 * what a person sees when a sign-in failed, and after signing out. A path set to `null`
 * is not registered, for an app that keeps its own route there.
 *
 * The callback stays the app's: it is where the app makes its own account and session.
 */
final class SignInRoutes
{
    /** Flashed by `CboxId::signInFailed()`: the error code the error page shows. */
    public const ERROR_KEY = 'cbox-id-client.sign-in.error';

    /** What each entry point is served at unless `cbox-id-client.sign_in` says otherwise. */
    private const PATHS = [
        'login_path' => '/login',
        'signup_path' => '/signup',
        'launch_path' => '/login/launch',
        'invitation_path' => '/login/invitation',
        'error_path' => '/login/error',
        'signed_out_path' => '/login/signed-out',
    ];

    public static function register(): void
    {
        if (SignInMode::configured() !== SignInMode::Redirect) {
            return;
        }

        Route::middleware(self::middleware())->group(static function (): void {
            foreach ([
                'login_path' => ['login', 'login'],
                'signup_path' => ['signup', 'signup'],
                'launch_path' => ['launch', 'login.launch'],
                'invitation_path' => ['invitation', 'login.invitation'],
                'error_path' => ['error', 'login.error'],
                'signed_out_path' => ['signedOut', 'login.signed-out'],
            ] as $key => [$action, $name]) {
                $path = self::path($key);

                if ($path !== null) {
                    Route::get($path, [SignInController::class, $action])->name($name);
                }
            }
        });
    }

    /**
     * The path of one entry point, or null when the app turned it off.
     *
     * A config file published before this block existed has no `sign_in` keys at all:
     * that is "the default", not "off". Only an explicit null or empty string is off.
     *
     * @param  key-of<self::PATHS>  $key
     */
    public static function path(string $key): ?string
    {
        $settings = config('cbox-id-client.sign_in');

        if (! is_array($settings) || ! array_key_exists($key, $settings)) {
            return self::PATHS[$key];
        }

        $path = $settings[$key];

        return is_string($path) && $path !== '' ? $path : null;
    }

    /** @return list<string> */
    private static function middleware(): array
    {
        $middleware = config('cbox-id-client.sign_in.middleware');

        if (! is_array($middleware)) {
            return ['web'];
        }

        return array_values(array_filter($middleware, static fn (mixed $m): bool => is_string($m) && $m !== ''));
    }
}
