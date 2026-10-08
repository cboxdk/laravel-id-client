<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/WhitelabelBranding` on the environment plane. */
readonly class WhitelabelBranding implements JsonSerializable
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $palette,
        /** null for the environment default. */
        public ?string $organizationId = null,
        public ?string $appName = null,
        public ?string $emailFromName = null,
        public ?string $emailTemplate = null,
        /** Uploaded from the console. */
        public ?string $logoUrl = null,
        /** Uploaded from the console. */
        public ?string $faviconUrl = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            palette: Field::required($data, 'palette', 'WhitelabelBranding', Value::object(...)),
            organizationId: Field::optional($data, 'organization_id', 'WhitelabelBranding', Value::string(...)),
            appName: Field::optional($data, 'app_name', 'WhitelabelBranding', Value::string(...)),
            emailFromName: Field::optional($data, 'email_from_name', 'WhitelabelBranding', Value::string(...)),
            emailTemplate: Field::optional($data, 'email_template', 'WhitelabelBranding', Value::string(...)),
            logoUrl: Field::optional($data, 'logo_url', 'WhitelabelBranding', Value::string(...)),
            faviconUrl: Field::optional($data, 'favicon_url', 'WhitelabelBranding', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'palette' => $this->palette,
            'app_name' => $this->appName,
            'email_from_name' => $this->emailFromName,
            'email_template' => $this->emailTemplate,
            'logo_url' => $this->logoUrl,
            'favicon_url' => $this->faviconUrl,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
