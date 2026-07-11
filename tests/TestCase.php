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
}
