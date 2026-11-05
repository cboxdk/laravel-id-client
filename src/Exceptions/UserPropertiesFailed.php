<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/** Status is null on transport failure. 401/403 must invalidate a consumer's mirror. */
class UserPropertiesFailed extends CboxIdException
{
    public function __construct(public readonly ?int $status, public readonly string $error)
    {
        parent::__construct('Cbox ID user properties could not be accessed.');
    }
}
