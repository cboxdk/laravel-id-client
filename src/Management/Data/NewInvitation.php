<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\OrganizationRole;

/**
 * `POST /v1/organizations/{id}/invitations`.
 *
 * `roles` are YOUR app's manifest roles, granted when the invitation is accepted, on top
 * of the built-in tier in `role`. `returnTo` sends the person back to your app after
 * accepting; Cbox ID validates it against the inviting app's registered redirect
 * origins, so name `clientId` (your app) when you set it.
 */
readonly class NewInvitation
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $email,
        public OrganizationRole $role = OrganizationRole::Member,
        public array $roles = [],
        public ?string $returnTo = null,
        public ?string $clientId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'email' => $this->email,
            'role' => $this->role->value,
            'roles' => $this->roles,
            'return_to' => $this->returnTo,
            'client_id' => $this->clientId,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
