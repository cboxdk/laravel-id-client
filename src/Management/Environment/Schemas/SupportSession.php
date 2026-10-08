<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SupportSession` on the environment plane. */
readonly class SupportSession implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** The customer's user being acted as. */
        public string $userId,
        public string $organizationId,
        public string $clientId,
        public string $actorId,
        /** The RFC 8693 actor claim every token of the session carries. */
        public SupportSessionAct $act,
        public string $reason,
        /** @var list<string> */
        public array $scopes,
        public string $expiresAt,
        /** The first authorization code, when `redirect_uri` and `code_challenge` were sent. Shown once. */
        public ?string $code = null,
        public ?string $redirectUri = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SupportSession', Value::string(...)),
            userId: Field::required($data, 'user_id', 'SupportSession', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'SupportSession', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'SupportSession', Value::string(...)),
            actorId: Field::required($data, 'actor_id', 'SupportSession', Value::string(...)),
            act: Field::required($data, 'act', 'SupportSession', Value::dto(SupportSessionAct::fromArray(...))),
            reason: Field::required($data, 'reason', 'SupportSession', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'SupportSession', Value::list(Value::string(...))),
            expiresAt: Field::required($data, 'expires_at', 'SupportSession', Value::string(...)),
            code: Field::optional($data, 'code', 'SupportSession', Value::string(...)),
            redirectUri: Field::optional($data, 'redirect_uri', 'SupportSession', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'client_id' => $this->clientId,
            'actor_id' => $this->actorId,
            'act' => $this->act->toArray(),
            'reason' => $this->reason,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
