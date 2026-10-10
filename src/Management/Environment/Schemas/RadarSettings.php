<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/RadarSettings` on the environment plane. */
readonly class RadarSettings implements JsonSerializable
{
    public function __construct(
        /**
         * monitor records every verdict and acts on none; enforce blocks and challenges.
         * One of `monitor`, `enforce`.
         */
        public string $mode,
        /** True while the environment follows the deployment's default (RISK_MODE) rather than a mode it chose. */
        public bool $modeInherited,
        /** One of `monitor`, `enforce`. */
        public string $deploymentMode,
        /**
         * The configured IP intelligence source. With none, country, network and travel facts are unknown and the rules on them never fire.
         * One of `none`, `maxmind`, `ipinfo`.
         */
        public string $ipIntelligence,
        /** @var list<RadarSettingsBuiltinRulesItem> */
        public array $builtinRules,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mode: Field::required($data, 'mode', 'RadarSettings', Value::string(...)),
            modeInherited: Field::required($data, 'mode_inherited', 'RadarSettings', Value::bool(...)),
            deploymentMode: Field::required($data, 'deployment_mode', 'RadarSettings', Value::string(...)),
            ipIntelligence: Field::required($data, 'ip_intelligence', 'RadarSettings', Value::string(...)),
            builtinRules: Field::required($data, 'builtin_rules', 'RadarSettings', Value::list(Value::dto(RadarSettingsBuiltinRulesItem::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'mode_inherited' => $this->modeInherited,
            'deployment_mode' => $this->deploymentMode,
            'ip_intelligence' => $this->ipIntelligence,
            'builtin_rules' => array_map(static fn (RadarSettingsBuiltinRulesItem $item) => $item->toArray(), $this->builtinRules),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
