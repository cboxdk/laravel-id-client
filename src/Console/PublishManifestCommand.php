<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Console;

use Cbox\Id\Client\Authz\ManifestPublisher;
use Illuminate\Console\Command;
use Throwable;

/**
 * Push this app's declared roles/permissions (config `cbox-id-client.authz`) to
 * Cbox ID. Run it on deploy so the console always reflects the app's current
 * catalog. Idempotent — an unchanged manifest is a server-side no-op.
 */
final class PublishManifestCommand extends Command
{
    protected $signature = 'cbox-id:publish-manifest';

    protected $description = "Publish this app's roles & permissions manifest to Cbox ID.";

    public function handle(ManifestPublisher $publisher): int
    {
        $manifest = $publisher->manifest();

        if ($manifest['roles'] === [] && $manifest['permissions'] === []) {
            $this->warn('Nothing to publish — declare roles/permissions in config/cbox-id-client.php under "authz".');

            return self::SUCCESS;
        }

        try {
            $result = $publisher->publish();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (($result['unchanged'] ?? false) === true) {
            $this->info('Manifest already up to date.');

            return self::SUCCESS;
        }

        $roles = is_int($result['roles_declared'] ?? null) ? $result['roles_declared'] : 0;
        $permissions = is_int($result['permissions_declared'] ?? null) ? $result['permissions_declared'] : 0;
        $this->info(sprintf('Manifest published — %d role(s), %d permission(s).', $roles, $permissions));

        return self::SUCCESS;
    }
}
