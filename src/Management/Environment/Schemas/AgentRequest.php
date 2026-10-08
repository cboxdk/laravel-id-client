<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A pending request from an agent to act as one of this environment's people (OIDC CIBA).
 *
 * `#/components/schemas/AgentRequest` on the environment plane.
 */
readonly class AgentRequest implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $clientId,
        /** The app's name, or its client id when it has none. */
        public string $app,
        /** Whom the agent asks to act as. */
        public AgentRequestSubject $subject,
        /** @var list<string> */
        public array $scopes,
        /** The message the person sees on their device, to recognise the request. */
        public ?string $bindingMessage = null,
        public ?string $expiresAt = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AgentRequest', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'AgentRequest', Value::string(...)),
            app: Field::required($data, 'app', 'AgentRequest', Value::string(...)),
            subject: Field::required($data, 'subject', 'AgentRequest', Value::dto(AgentRequestSubject::fromArray(...))),
            scopes: Field::required($data, 'scopes', 'AgentRequest', Value::list(Value::string(...))),
            bindingMessage: Field::optional($data, 'binding_message', 'AgentRequest', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'AgentRequest', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'AgentRequest', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'app' => $this->app,
            'subject' => $this->subject->toArray(),
            'binding_message' => $this->bindingMessage,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
