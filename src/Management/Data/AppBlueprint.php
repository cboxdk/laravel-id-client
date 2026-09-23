<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * An app's portable configuration, exported to promote it from one environment to
 * another (staging → production): its settings, scopes and manifest — never its secrets.
 *
 * The document is kept exactly as Cbox ID wrote it, because it is meant to be handed
 * back to Cbox ID, and re-shaping it here would be a second definition of a format this
 * SDK does not own.
 */
readonly class AppBlueprint
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        public string $appId,
        public array $document,
    ) {}

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->document, $flags | JSON_THROW_ON_ERROR);
    }
}
