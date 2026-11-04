<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\UserProperties\PropertyDocument;
use Cbox\Id\Client\UserProperties\PropertyPatch;
use Cbox\Id\Client\UserProperties\PropertyToken;

interface UserProperties
{
    public function read(string $user, string $namespace, PropertyToken $token): PropertyDocument;

    public function patch(string $user, string $namespace, PropertyPatch $patch, PropertyToken $token): PropertyDocument;
}
