<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Tests;

use Cbox\Id\Client\ClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ClientServiceProvider::class];
    }

    /**
     * An application key, because routes in the `web` group encrypt cookies — the
     * tenancy middleware is exercised the way a browser reaches it.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }
}
