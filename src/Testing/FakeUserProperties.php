<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\Exceptions\UserPropertiesFailed;
use Cbox\Id\Client\UserProperties\PropertyDocument;
use Cbox\Id\Client\UserProperties\PropertyPatch;
use Cbox\Id\Client\UserProperties\PropertyToken;
use LogicException;

class FakeUserProperties implements UserProperties
{
    /** @var list<array{method:string,user:string,namespace:string,patch:PropertyPatch|null}> */
    public array $calls = [];

    /** @param list<PropertyDocument|UserPropertiesFailed> $results */
    public function __construct(private array $results) {}

    public function read(string $user, string $namespace, PropertyToken $token): PropertyDocument
    {
        return $this->next('GET', $user, $namespace);
    }

    public function patch(string $user, string $namespace, PropertyPatch $patch, PropertyToken $token): PropertyDocument
    {
        return $this->next('PATCH', $user, $namespace, $patch);
    }

    private function next(string $method, string $user, string $namespace, ?PropertyPatch $patch = null): PropertyDocument
    {
        $this->calls[] = ['method' => $method, 'user' => $user, 'namespace' => $namespace, 'patch' => $patch];
        if ($this->results === []) {
            throw new LogicException('No user-property result was queued.');
        }
        $result = array_shift($this->results);
        if ($result instanceof UserPropertiesFailed) {
            throw $result;
        }
        if ($result->userId !== $user || $result->namespace !== $namespace) {
            throw new LogicException('The queued property result belongs to a different subject or namespace.');
        }

        return $result;
    }
}
