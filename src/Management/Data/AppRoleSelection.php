<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/** The reviewed assignment set for one app; its version prevents stale forms restoring access. */
readonly class AppRoleSelection
{
    /** @param list<RoleAssignment> $roles */
    public function __construct(public string $clientId, public array $roles) {}

    /** @param list<RoleAssignment> $assignments */
    public static function fromAssignments(string $clientId, array $assignments): self
    {
        return new self($clientId, array_values(array_filter($assignments, static fn (RoleAssignment $role): bool => $role->clientId === $clientId)));
    }

    public function version(): string
    {
        $ids = array_values(array_unique(array_map(static fn (RoleAssignment $role): string => $role->roleId, $this->roles)));
        sort($ids);

        return hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->roles as $role) {
            if ($role->key !== null) {
                $keys[] = $role->key;
            }
        }

        return array_values(array_unique($keys));
    }
}
