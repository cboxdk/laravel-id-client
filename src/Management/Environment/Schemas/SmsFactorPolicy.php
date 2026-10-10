<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SmsFactorPolicy` on the environment plane. */
readonly class SmsFactorPolicy implements JsonSerializable
{
    public function __construct(
        /** Text-message codes are accepted as a second factor. Off by default. */
        public bool $enabled,
        /**
         * ISO 3166-1 alpha-2 countries whose numbers may enrol and be texted. Re-checked at every send.
         *
         * @var list<string>
         */
        public array $allowedCountries,
        /** Administrators may add SMS only beside an authenticator app or a passkey. */
        public bool $privilegedNeedStrongerFactor,
        /**
         * The deployment's ceiling (CBOX_ID_SMS_ALLOWED_COUNTRIES). Empty: no deployment restriction. A listed country outside it is never texted.
         *
         * @var list<string>
         */
        public array $deploymentCountries,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: Field::required($data, 'enabled', 'SmsFactorPolicy', Value::bool(...)),
            allowedCountries: Field::required($data, 'allowed_countries', 'SmsFactorPolicy', Value::list(Value::string(...))),
            privilegedNeedStrongerFactor: Field::required($data, 'privileged_need_stronger_factor', 'SmsFactorPolicy', Value::bool(...)),
            deploymentCountries: Field::required($data, 'deployment_countries', 'SmsFactorPolicy', Value::list(Value::string(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'allowed_countries' => $this->allowedCountries,
            'privileged_need_stronger_factor' => $this->privilegedNeedStrongerFactor,
            'deployment_countries' => $this->deploymentCountries,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
