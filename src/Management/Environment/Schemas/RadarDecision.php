<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/RadarDecision` on the environment plane. */
readonly class RadarDecision implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $assessedAt,
        /** One of `sign_in`, `sign_up`. */
        public string $flow,
        /** One of `allow`, `challenge`, `block`. */
        public string $verdict,
        /** Whether the verdict was acted on; false under monitor. */
        public bool $enforced,
        /** One of `monitor`, `enforce`. */
        public string $mode,
        /**
         * Every rule that fired, the deciding one included.
         *
         * @var list<RadarDecisionTriggeredItem>
         */
        public array $triggered,
        /** @var list<string> */
        public array $reasons,
        public int|float $riskScore,
        /** One of `allow`, `flag`, `challenge`, `step_up`, `reject`. */
        public string $riskOutcome,
        /**
         * The facts the rules were evaluated on — never the IP, the address or the user agent.
         *
         * @var array<string, mixed>
         */
        public array $facts,
        /** One of `password`, `magic_link`, `passkey`, `sign_up`. */
        public ?string $method = null,
        /** What decided it: deny_list:<kind>, allow_list:<kind>, rule:<id>, builtin:<key>; null when nothing matched. */
        public ?string $rule = null,
        public ?string $ruleName = null,
        public ?string $country = null,
        public ?int $asn = null,
        public ?string $emailDomain = null,
        /** The device id (a pseudonym of the device cookie), for a returning device. */
        public ?string $device = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'RadarDecision', Value::string(...)),
            assessedAt: Field::required($data, 'assessed_at', 'RadarDecision', Value::string(...)),
            flow: Field::required($data, 'flow', 'RadarDecision', Value::string(...)),
            verdict: Field::required($data, 'verdict', 'RadarDecision', Value::string(...)),
            enforced: Field::required($data, 'enforced', 'RadarDecision', Value::bool(...)),
            mode: Field::required($data, 'mode', 'RadarDecision', Value::string(...)),
            triggered: Field::required($data, 'triggered', 'RadarDecision', Value::list(Value::dto(RadarDecisionTriggeredItem::fromArray(...)))),
            reasons: Field::required($data, 'reasons', 'RadarDecision', Value::list(Value::string(...))),
            riskScore: Field::required($data, 'risk_score', 'RadarDecision', Value::number(...)),
            riskOutcome: Field::required($data, 'risk_outcome', 'RadarDecision', Value::string(...)),
            facts: Field::required($data, 'facts', 'RadarDecision', Value::object(...)),
            method: Field::optional($data, 'method', 'RadarDecision', Value::string(...)),
            rule: Field::optional($data, 'rule', 'RadarDecision', Value::string(...)),
            ruleName: Field::optional($data, 'rule_name', 'RadarDecision', Value::string(...)),
            country: Field::optional($data, 'country', 'RadarDecision', Value::string(...)),
            asn: Field::optional($data, 'asn', 'RadarDecision', Value::int(...)),
            emailDomain: Field::optional($data, 'email_domain', 'RadarDecision', Value::string(...)),
            device: Field::optional($data, 'device', 'RadarDecision', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'assessed_at' => $this->assessedAt,
            'flow' => $this->flow,
            'method' => $this->method,
            'verdict' => $this->verdict,
            'enforced' => $this->enforced,
            'mode' => $this->mode,
            'rule' => $this->rule,
            'rule_name' => $this->ruleName,
            'triggered' => array_map(static fn (RadarDecisionTriggeredItem $item) => $item->toArray(), $this->triggered),
            'reasons' => $this->reasons,
            'risk_score' => $this->riskScore,
            'risk_outcome' => $this->riskOutcome,
            'country' => $this->country,
            'asn' => $this->asn,
            'email_domain' => $this->emailDomain,
            'device' => $this->device,
            'facts' => $this->facts,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
