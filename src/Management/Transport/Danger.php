<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/** How much harm an action can do, as the server declares it (`x-danger`). */
enum Danger: string
{
    case Read = 'read';
    case Write = 'write';
    case Destructive = 'destructive';
    case Critical = 'critical';
}
