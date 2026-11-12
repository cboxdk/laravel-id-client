<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\SyncQueue;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Work that keeps the app's local copies of Cbox ID's answers current, outside the
 * request a person is waiting on.
 *
 * Two ways to leave the request: {@see afterResponse()} runs a closure once the
 * response has been sent (or the job or command has finished), in this process;
 * {@see dispatch()} puts a job on the queue. Neither ever throws at the caller: a
 * refresh that cannot be scheduled is reported and the kept copy stays.
 *
 * @internal
 */
final class Background
{
    /**
     * Run `$work` after the response. Calls with the same name in one request run once.
     * An error is reported, never thrown: the response it would fail has already left.
     */
    public static function afterResponse(string $name, Closure $work): void
    {
        try {
            defer(static function () use ($work): void {
                try {
                    $work();
                } catch (Throwable $error) {
                    report($error);
                }
            }, $name, always: true);
        } catch (Throwable $error) {
            report($error);
        }
    }

    /**
     * Queue `$job` on the given connection and queue, falling back to
     * `cbox-id-client.background.queue_connection` / `.queue`, then the app's defaults.
     *
     * On the `sync` driver a dispatched job would run right here, inside the request,
     * which is the wait this class exists to remove: there `$inline` runs after the
     * response instead.
     *
     * @param  Closure(): PendingDispatch  $job  dispatches the job: `fn () => SomeJob::dispatch(…)`
     * @param  Closure(): void  $inline  the same work, for an app with no queue worker
     */
    public static function dispatch(string $name, Closure $job, Closure $inline, ?string $connection = null, ?string $queue = null): void
    {
        try {
            $connection ??= self::config('cbox-id-client.background.queue_connection');
            $queue ??= self::config('cbox-id-client.background.queue');

            // The connection itself, not its config: `Queue::fake()` and an app's own
            // driver both answer here. An unknown connection throws, and is reported.
            if (Container::getInstance()->make(QueueFactory::class)->connection($connection) instanceof SyncQueue) {
                self::afterResponse($name, $inline);

                return;
            }

            $pending = $job();

            if ($connection !== null) {
                $pending->onConnection($connection);
            }

            if ($queue !== null) {
                $pending->onQueue($queue);
            }

            // The job is sent when the pending dispatch is destroyed: do it here, so a
            // queue that is down is reported instead of failing the page.
            unset($pending);
        } catch (Throwable $error) {
            report($error);
        }
    }

    private static function config(string $key): ?string
    {
        $container = Container::getInstance();
        $config = $container->bound('config') ? $container->make('config') : null;
        $value = $config instanceof Repository ? $config->get($key) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
