<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A SAML connection's signing certificates. The certificates themselves are never returned.
 *
 * `#/components/schemas/SsoCertificates` on the environment plane.
 */
readonly class SsoCertificates implements JsonSerializable
{
    public function __construct(
        public string $connectionId,
        /** @var list<SsoCertificatesCertificatesItem> */
        public array $certificates,
        public ?string $organizationId = null,
        /** When the connection stops working if nothing is done: the latest expiry among the certificates it trusts. */
        public ?string $expiresAt = null,
        public ?int $daysRemaining = null,
        /**
         * On the stage answer only: what the new certificate was tested for.
         *
         * @var list<SsoCertificatesChecksItem>
         */
        public ?array $checks = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            connectionId: Field::required($data, 'connection_id', 'SsoCertificates', Value::string(...)),
            certificates: Field::required($data, 'certificates', 'SsoCertificates', Value::list(Value::dto(SsoCertificatesCertificatesItem::fromArray(...)))),
            organizationId: Field::optional($data, 'organization_id', 'SsoCertificates', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'SsoCertificates', Value::string(...)),
            daysRemaining: Field::optional($data, 'days_remaining', 'SsoCertificates', Value::int(...)),
            checks: Field::optional($data, 'checks', 'SsoCertificates', Value::list(Value::dto(SsoCertificatesChecksItem::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'connection_id' => $this->connectionId,
            'organization_id' => $this->organizationId,
            'expires_at' => $this->expiresAt,
            'days_remaining' => $this->daysRemaining,
            'certificates' => array_map(static fn (SsoCertificatesCertificatesItem $item) => $item->toArray(), $this->certificates),
            'checks' => $this->checks === null ? null : array_map(static fn (SsoCertificatesChecksItem $item) => $item->toArray(), $this->checks),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
