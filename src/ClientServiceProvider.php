<?php

declare(strict_types=1);

namespace Cbox\Id\Client;

use Cbox\Id\Client\Authz\ManifestPublisher;
use Cbox\Id\Client\Console\PublishManifestCommand;
use Cbox\Id\Client\Support\Discovery;
use Illuminate\Support\ServiceProvider;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cbox-id-client.php', 'cbox-id-client');

        $this->app->singleton(ManifestPublisher::class, static function (): ManifestPublisher {
            $issuer = self::configString('cbox-id-client.issuer');
            $cacheTtl = self::configInt('cbox-id-client.cache_ttl', 3600);
            $timeout = self::configInt('cbox-id-client.http_timeout', 10);

            return new ManifestPublisher(
                new Discovery($issuer, $cacheTtl, $timeout),
                $issuer,
                self::configString('cbox-id-client.client_id'),
                self::configString('cbox-id-client.client_secret'),
                self::configListOfArrays('cbox-id-client.authz.permissions'),
                self::configListOfArrays('cbox-id-client.authz.roles'),
                $timeout,
            );
        });

        $this->app->singleton(IdentityClient::class, static function (): IdentityClient {
            $raw = config('cbox-id-client');
            $config = [];

            if (is_array($raw)) {
                foreach ($raw as $key => $value) {
                    if (is_string($key)) {
                        $config[$key] = $value;
                    }
                }
            }

            $issuer = is_string($config['issuer'] ?? null) ? $config['issuer'] : '';
            $cacheTtl = is_numeric($config['cache_ttl'] ?? null) ? (int) $config['cache_ttl'] : 3600;
            $timeout = is_numeric($config['http_timeout'] ?? null) ? (int) $config['http_timeout'] : 10;

            return new IdentityClient($config, new Discovery($issuer, $cacheTtl, $timeout));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishManifestCommand::class]);

            $this->publishes([
                __DIR__.'/../config/cbox-id-client.php' => config_path('cbox-id-client.php'),
            ], 'cbox-id-client-config');
        }
    }

    private static function configString(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    private static function configInt(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function configListOfArrays(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $normalized = [];
                foreach ($entry as $k => $v) {
                    if (is_string($k)) {
                        $normalized[$k] = $v;
                    }
                }
                $list[] = $normalized;
            }
        }

        return $list;
    }
}
