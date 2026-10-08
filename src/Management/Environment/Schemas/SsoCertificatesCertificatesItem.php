<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SsoCertificatesCertificatesItem` on the environment plane. */
readonly class SsoCertificatesCertificatesItem implements JsonSerializable
{
    public function __construct(
        /**
         * primary signs today; staged is trusted beside it until activated.
         * One of `primary`, `staged`.
         */
        public string $role,
        /** false for a certificate on file that could not be parsed — still trusted. */
        public bool $readable,
        public ?string $fingerprintSha256 = null,
        public ?string $subject = null,
        public ?string $issuer = null,
        public ?string $notBefore = null,
        public ?string $notAfter = null,
        public ?int $daysRemaining = null,
        public ?bool $expired = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            role: Field::required($data, 'role', 'SsoCertificatesCertificatesItem', Value::string(...)),
            readable: Field::required($data, 'readable', 'SsoCertificatesCertificatesItem', Value::bool(...)),
            fingerprintSha256: Field::optional($data, 'fingerprint_sha256', 'SsoCertificatesCertificatesItem', Value::string(...)),
            subject: Field::optional($data, 'subject', 'SsoCertificatesCertificatesItem', Value::string(...)),
            issuer: Field::optional($data, 'issuer', 'SsoCertificatesCertificatesItem', Value::string(...)),
            notBefore: Field::optional($data, 'not_before', 'SsoCertificatesCertificatesItem', Value::string(...)),
            notAfter: Field::optional($data, 'not_after', 'SsoCertificatesCertificatesItem', Value::string(...)),
            daysRemaining: Field::optional($data, 'days_remaining', 'SsoCertificatesCertificatesItem', Value::int(...)),
            expired: Field::optional($data, 'expired', 'SsoCertificatesCertificatesItem', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'readable' => $this->readable,
            'fingerprint_sha256' => $this->fingerprintSha256,
            'subject' => $this->subject,
            'issuer' => $this->issuer,
            'not_before' => $this->notBefore,
            'not_after' => $this->notAfter,
            'days_remaining' => $this->daysRemaining,
            'expired' => $this->expired,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
