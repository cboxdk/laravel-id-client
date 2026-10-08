<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

/**
 * One management plane the generator builds a client for.
 */
final readonly class PlaneConfig
{
    /**
     * @param  string  $plane  the `Plane` case value
     * @param  string  $file  the vendored spec, `openapi/{file}.yaml`
     * @param  string  $className  the generated client
     * @param  string  $namespace  the sub-namespace (and directory) its resources and schemas live in
     * @param  string  $specPath  where the server serves this spec, relative to the host
     * @param  list<string>  $schemes  the security schemes that are this plane's management credentials
     * @param  string  $prefix  path prefix stripped when a non-action route's name is derived from its path
     */
    public function __construct(
        public string $plane,
        public string $file,
        public string $className,
        public string $namespace,
        public string $specPath,
        public array $schemes,
        public string $prefix,
    ) {}

    /** @return list<self> */
    public static function all(): array
    {
        return [
            new self('environment', 'environment', 'EnvironmentClient', 'Environment', '/api/v1/environment/openapi.yaml', ['EnvironmentApiKey', 'ManagementAccessToken', 'WorkspaceAccessToken'], ''),
            new self('workspace', 'workspace', 'WorkspaceClient', 'Workspace', '/api/v1/workspace/openapi.yaml', ['OrganizationApiKey', 'WorkspaceApiKey', 'WorkspaceAccessToken'], '/workspace'),
            new self('platform', 'platform', 'PlatformClient', 'Platform', '/api/v1/platform/openapi.yaml', ['OperatorToken'], '/platform'),
            new self('account', 'account', 'AccountClient', 'Account', '/api/v1/me/openapi.yaml', ['PersonToken'], '/me'),
        ];
    }
}
