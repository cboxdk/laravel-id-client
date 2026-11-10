<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

/** The Cbox ID person and organization a catalog belongs to. */
final readonly class AppCatalogContext
{
    public function __construct(
        public string $subject,
        public string $organizationId,
    ) {}
}
