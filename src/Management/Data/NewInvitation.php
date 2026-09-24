<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\AssignableMemberRole;

/**
 * `POST /v1/organizations/{id}/invitations`.
 *
 * - `role` — `admin` or `member`; never `owner` (ownership moves by transfer).
 * - `roles` — access roles granted on acceptance: role ids, or YOUR app's manifest keys
 *   when `clientId` names the app that declared them. A staff role is refused here; grant
 *   it after they join.
 * - `clientId` + `returnTo` — after accepting, the person is sent to `returnTo`, which must
 *   be on one of that app's registered redirect-URI origins.
 * - `inviterName` — who the mail says it is from (defaults to the app's or environment's name).
 */
readonly class NewInvitation
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $email,
        public AssignableMemberRole $role = AssignableMemberRole::Member,
        public array $roles = [],
        public ?string $returnTo = null,
        public ?string $clientId = null,
        public ?string $inviterName = null,
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
            'inviter_name' => $this->inviterName,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
