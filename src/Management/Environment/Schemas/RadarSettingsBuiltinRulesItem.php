<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `RadarSettingsBuiltinRulesItem` on the environment plane. */
readonly class RadarSettingsBuiltinRulesItem implements JsonSerializable
{
    public function __construct(
        /** One of `credential_stuffing`, `bot_velocity`, `account_attack`, `impossible_travel`, `new_device`, `anonymous_network`, `hosting_network`, `disposable_email`, `risk_score_reject`, `risk_score_elevated`. */
        public string $key,
        public string $name,
        public string $description,
        /** One of `all`, `sign_in`, `sign_up`. */
        public string $appliesTo,
        public bool $enabled,
        /** One of `allow`, `challenge`, `block`. */
        public string $action,
        /** null for a rule that counts nothing. */
        public ?int $threshold = null,
        public ?string $thresholdUnit = null,
        public ?int $thresholdMin = null,
        public ?int $thresholdMax = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Field::required($data, 'key', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            name: Field::required($data, 'name', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            description: Field::required($data, 'description', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            appliesTo: Field::required($data, 'applies_to', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            enabled: Field::required($data, 'enabled', 'RadarSettingsBuiltinRulesItem', Value::bool(...)),
            action: Field::required($data, 'action', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            threshold: Field::optional($data, 'threshold', 'RadarSettingsBuiltinRulesItem', Value::int(...)),
            thresholdUnit: Field::optional($data, 'threshold_unit', 'RadarSettingsBuiltinRulesItem', Value::string(...)),
            thresholdMin: Field::optional($data, 'threshold_min', 'RadarSettingsBuiltinRulesItem', Value::int(...)),
            thresholdMax: Field::optional($data, 'threshold_max', 'RadarSettingsBuiltinRulesItem', Value::int(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'applies_to' => $this->appliesTo,
            'enabled' => $this->enabled,
            'action' => $this->action,
            'threshold' => $this->threshold,
            'threshold_unit' => $this->thresholdUnit,
            'threshold_min' => $this->thresholdMin,
            'threshold_max' => $this->thresholdMax,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
