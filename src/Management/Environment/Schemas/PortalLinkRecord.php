<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * An Admin Portal link as a list shows it. Never its URL: that was shown once, when it was minted, and only its hash is kept.
 *
 * `#/components/schemas/PortalLinkRecord` on the environment plane.
 */
readonly class PortalLinkRecord implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        /**
         * What the link may set up.
         *
         * @var list<string>
         */
        public array $intents,
        /**
         * `pending`: not opened yet, and it still can be. `in_use`: opened, and the setup
         * session it started may still be running. `completed`: its setup was finished.
         * `expired`: never opened in time, or its setup session ran out. `revoked`: withdrawn.
         * One of `pending`, `in_use`, `completed`, `expired`, `revoked`.
         */
        public string $status,
        /** Who minted it: the console person's id, or the management key's. */
        public string $createdBy,
        public ?string $createdAt = null,
        /** The address it was mailed to, or null when it was not sent. */
        public ?string $emailedTo = null,
        /** How long it could wait to be opened. */
        public ?string $expiresAt = null,
        /** When it was opened. It is single-use. */
        public ?string $consumedAt = null,
        /** When its setup was finished. */
        public ?string $completedAt = null,
        /** When it was withdrawn. */
        public ?string $revokedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PortalLinkRecord', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'PortalLinkRecord', Value::string(...)),
            intents: Field::required($data, 'intents', 'PortalLinkRecord', Value::list(Value::string(...))),
            status: Field::required($data, 'status', 'PortalLinkRecord', Value::string(...)),
            createdBy: Field::required($data, 'created_by', 'PortalLinkRecord', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'PortalLinkRecord', Value::string(...)),
            emailedTo: Field::optional($data, 'emailed_to', 'PortalLinkRecord', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'PortalLinkRecord', Value::string(...)),
            consumedAt: Field::optional($data, 'consumed_at', 'PortalLinkRecord', Value::string(...)),
            completedAt: Field::optional($data, 'completed_at', 'PortalLinkRecord', Value::string(...)),
            revokedAt: Field::optional($data, 'revoked_at', 'PortalLinkRecord', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'intents' => $this->intents,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
            'emailed_to' => $this->emailedTo,
            'expires_at' => $this->expiresAt,
            'consumed_at' => $this->consumedAt,
            'completed_at' => $this->completedAt,
            'revoked_at' => $this->revokedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
