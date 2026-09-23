<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

readonly class Invitation
{
    /**
     * @param  list<string>  $roles  app roles granted on acceptance
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $email,
        public ?OrganizationRole $role = null,
        public array $roles = [],
        public ?string $organizationId = null,
        public ?string $status = null,
        public ?string $returnTo = null,
        public ?string $clientId = null,
        public ?DateTimeImmutable $expiresAt = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $role = Claims::string($data, 'role');

        return new self(
            id: Claims::requiredString($data, 'id'),
            email: Claims::requiredString($data, 'email'),
            role: $role !== null ? OrganizationRole::tryFrom($role) : null,
            roles: Claims::strings($data, 'roles'),
            organizationId: Claims::string($data, 'organization_id'),
            status: Claims::string($data, 'status'),
            returnTo: Claims::string($data, 'return_to'),
            clientId: Claims::string($data, 'client_id'),
            expiresAt: Claims::time($data, 'expires_at'),
            attributes: $data,
        );
    }
}
