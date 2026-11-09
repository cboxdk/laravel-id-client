<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Cbox\Id\Client\Decisions\KeptDecisions;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;

/**
 * How long any call to Cbox ID may spend opening a connection.
 *
 * Every HTTP call the package makes carries this connect timeout next to its overall
 * timeout. A Cbox ID that cannot be reached then fails a call in a few seconds instead
 * of holding a worker for the whole request timeout, which is what lets a caller fall
 * back to a kept answer quickly ({@see KeptDecisions}).
 *
 * Read from `cbox-id-client.http_connect_timeout` (`CBOX_ID_HTTP_CONNECT_TIMEOUT`),
 * default three seconds, never below one.
 */
class HttpTimeouts
{
    public const int DEFAULT_CONNECT_SECONDS = 3;

    public static function connect(): int
    {
        $container = Container::getInstance();
        $config = $container->bound('config') ? $container->make('config') : null;
        $value = $config instanceof Repository ? $config->get('cbox-id-client.http_connect_timeout') : null;

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_CONNECT_SECONDS;
    }
}
