<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Console;

use Cbox\Id\Client\IdentityClient;
use Illuminate\Console\Command;

/**
 * Keeps what this app holds of Cbox ID current outside a person's request: fetches the
 * discovery document and the JWKS again when they are older than `cache_ttl`, and
 * renews the machine tokens in use that are about to expire.
 *
 * The package schedules it every five minutes (`cbox-id-client.background.schedule`),
 * so an app that runs `schedule:run` needs nothing else. Without a scheduler nothing
 * breaks: a request then renews what it finds stale after its response, as before.
 */
class RefreshCommand extends Command
{
    protected $signature = 'cbox-id:refresh';

    protected $description = 'Refresh the kept Cbox ID discovery document, signing keys and machine tokens.';

    public function handle(): int
    {
        $issuer = config('cbox-id-client.issuer');
        $clientId = config('cbox-id-client.client_id');

        if (! is_string($issuer) || $issuer === '' || ! is_string($clientId) || $clientId === '') {
            $this->line('Cbox ID is not configured; nothing to refresh.');

            return self::SUCCESS;
        }

        $result = $this->laravel->make(IdentityClient::class)->refreshKept();

        $this->line(sprintf(
            'Discovery and keys fetched: %d. Machine tokens renewed: %d.',
            $result['discovery'],
            $result['machine_tokens'],
        ));

        if ($result['failed'] > 0) {
            $this->error('Cbox ID could not be reached for discovery; the kept copy stays.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
