<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\AppCatalog\Http\AppCatalogController;
use Illuminate\Support\Facades\Route;

/**
 * The app switcher's routes, from `cbox-id-client.app_catalog`:
 *
 *  - GET `path` (`/apps/catalog`)                  cbox-id.apps.catalog
 *  - GET `authorize_path` (`/apps/authorize`)      cbox-id.apps.authorize
 *  - GET `callback_path` (`/apps/callback`)        cbox-id.apps.callback
 *  - GET `signin_path` (`/apps/signin`)            cbox-id.apps.signin
 *
 * The first three need a signed-in person (`middleware`); sign-in must not. Each blocks
 * the session, so a browser's requests to them run one at a time.
 */
final class AppCatalogRoutes
{
    public static function register(): void
    {
        Route::middleware(self::middleware('middleware', ['web', 'auth']))->group(static function (): void {
            Route::get(self::path('path', '/apps/catalog'), [AppCatalogController::class, 'index'])
                ->block()->name('cbox-id.apps.catalog');
            Route::get(self::path('authorize_path', '/apps/authorize'), [AppCatalogController::class, 'authorize'])
                ->block()->name('cbox-id.apps.authorize');
            Route::get(self::path('callback_path', '/apps/callback'), [AppCatalogController::class, 'callback'])
                ->block()->name('cbox-id.apps.callback');
        });

        Route::middleware(self::middleware('signin_middleware', ['web']))
            ->get(self::path('signin_path', '/apps/signin'), [AppCatalogController::class, 'signin'])
            ->middleware('throttle:20,1')->name('cbox-id.apps.signin');
    }

    private static function path(string $key, string $default): string
    {
        $path = config('cbox-id-client.app_catalog.'.$key);

        return is_string($path) && $path !== '' ? $path : $default;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function middleware(string $key, array $default): array
    {
        $middleware = config('cbox-id-client.app_catalog.'.$key);

        if (! is_array($middleware)) {
            return $default;
        }

        return array_values(array_filter($middleware, static fn (mixed $m): bool => is_string($m) && $m !== ''));
    }
}
