<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/** The four management planes, each with its own OpenAPI document and credentials. */
enum Plane: string
{
    /** One environment's tenancy, on its own host: `cbid_env_…` keys or a delegated token. */
    case Environment = 'environment';

    /** The workspace above its environments: `cbid_ws_…` keys or a person's root token. */
    case Workspace = 'workspace';

    /** The deployment itself, for operators: a delegated operator token only. */
    case Platform = 'platform';

    /** A person's own account: a delegated token only. */
    case Account = 'account';

    /** The prefix of this plane's management keys; null for a plane that takes none. */
    public function keyPrefix(): ?string
    {
        return match ($this) {
            self::Environment => 'cbid_env_',
            self::Workspace => 'cbid_ws_',
            self::Platform, self::Account => null,
        };
    }

    /** Where this plane serves `GET …/action-approvals/{id}`, relative to `/api/v1`. */
    public function approvalMount(): string
    {
        return match ($this) {
            self::Environment => '',
            self::Workspace => '/workspace',
            self::Platform => '/platform',
            self::Account => '/me',
        };
    }
}
