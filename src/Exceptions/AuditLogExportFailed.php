<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Management\Environment\Schemas\AuditLogExport;

/** An audit log export ended `failed` or `expired`, or was not ready in time. */
class AuditLogExportFailed extends CboxIdException
{
    public function __construct(string $message, public readonly ?AuditLogExport $export = null)
    {
        parent::__construct($message);
    }
}
