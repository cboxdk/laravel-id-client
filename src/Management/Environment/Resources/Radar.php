<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `radar.*` on the environment plane. */
class Radar
{
    public readonly RadarDecisions $decisions;

    public readonly RadarLists $lists;

    public readonly RadarMode $mode;

    public readonly RadarRules $rules;

    public readonly RadarSettings $settings;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->decisions = new RadarDecisions($transport);
        $this->lists = new RadarLists($transport);
        $this->mode = new RadarMode($transport);
        $this->rules = new RadarRules($transport);
        $this->settings = new RadarSettings($transport);
    }
}
