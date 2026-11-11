<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Facades;

use Cbox\Id\Client\Management\ManagementClients;
use Illuminate\Support\Facades\Facade;

/**
 * The typed management clients, built from `config/cbox-id-client.php`:
 *
 *     CboxIdApi::environment()->apps->secrets->rotate($appId, ['grace_seconds' => 3600]);
 *     CboxIdApi::organization()->environments->create(['name' => 'Staging', 'type' => 'sandbox']);
 *     CboxIdApi::auditLogger()->record([...]);
 *
 * @method static \Cbox\Id\Client\Management\EnvironmentClient environment()
 * @method static \Cbox\Id\Client\Management\OrganizationClient organization()
 * @method static \Cbox\Id\Client\Management\WorkspaceClient workspace() Deprecated: use organization().
 * @method static \Cbox\Id\Client\Management\EnvironmentClient environmentAs(string|\Closure $accessToken, ?string $environment = null, ?string $baseUrl = null)
 * @method static \Cbox\Id\Client\Management\OrganizationClient organizationAs(string|\Closure $accessToken, ?string $baseUrl = null)
 * @method static \Cbox\Id\Client\Management\WorkspaceClient workspaceAs(string|\Closure $accessToken, ?string $baseUrl = null) Deprecated: use organizationAs().
 * @method static \Cbox\Id\Client\Management\PlatformClient platform(string|\Closure $accessToken, ?string $baseUrl = null)
 * @method static \Cbox\Id\Client\Management\AccountClient account(string|\Closure $accessToken, ?string $baseUrl = null)
 * @method static \Cbox\Id\Client\Management\AuditLogs\AuditLogs auditLogs()
 * @method static \Cbox\Id\Client\Management\AuditLogs\AuditLogger auditLogger()
 *
 * @see ManagementClients
 */
class CboxIdApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ManagementClients::class;
    }
}
