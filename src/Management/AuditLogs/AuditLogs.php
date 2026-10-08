<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\AuditLogs;

use Cbox\Id\Client\Exceptions\AuditLogExportFailed;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogExport;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Ergonomics for the environment plane's Audit Logs product, on top of the generated
 * `$env->auditLogs->…` methods: a buffered sender, an export that waits until it is
 * ready, and a client-side check of an organization's hash chain.
 */
class AuditLogs
{
    public function __construct(private readonly EnvironmentClient $client) {}

    /**
     * A buffered sender for this environment.
     *
     * @param  (Closure(Throwable, list<array<string, mixed>>): void)|null  $onError
     */
    public function logger(int $batchSize = AuditLogger::MAX_BATCH, ?Closure $onError = null): AuditLogger
    {
        return new AuditLogger($this->client, $batchSize, $onError);
    }

    /**
     * Start a CSV export (`audit_logs.exports.create`) and read it until it is `ready`. The
     * export's `url` is signed and short-lived: download it straight away, or read the
     * export again (`$env->auditLogs->exports->get($id)`) for a fresh one.
     *
     * @param  array{organization_id?: string, actions?: list<string>, actor_id?: string, target_id?: string, range_start?: string, range_end?: string}  $filters
     * @param  string|null  $idempotencyKey  the create call's key — pass the one you used before to resume that export
     *
     * @throws AuditLogExportFailed when it ends `failed` or `expired`, or is not ready within `$timeoutMs`
     */
    public function export(array $filters = [], int $pollIntervalMs = 2000, int $timeoutMs = 600_000, ?string $idempotencyKey = null): AuditLogExport
    {
        $current = $this->client->auditLogs->exports->create($filters, new CallOptions(idempotencyKey: $idempotencyKey))->data;
        $deadline = Carbon::now()->getTimestampMs() + $timeoutMs;

        for (; ;) {
            if ($current->state === 'ready') {
                return $current;
            }

            if (in_array($current->state, ['failed', 'expired'], true)) {
                throw new AuditLogExportFailed("Audit log export {$current->id} is {$current->state}.", $current);
            }

            if (Carbon::now()->getTimestampMs() >= $deadline) {
                throw new AuditLogExportFailed("Audit log export {$current->id} was not ready in time.", $current);
            }

            if ($pollIntervalMs > 0) {
                Sleep::usleep($pollIntervalMs * 1000);
            }

            $current = $this->client->auditLogs->exports->get($current->id)->data;
        }
    }

    /**
     * Read every event of one organization (`audit_logs.events.list`, oldest first) and
     * verify its chain on this side, independently of the server's own check
     * (`$env->auditLogs->verify()`). The events are held in memory to be put in sequence
     * order, so this suits a chain of thousands, not millions; for a long one, use the
     * server's check, which pages by sequence.
     */
    public function verifyChain(string $organizationId, ?string $previousHash = null): ChainVerification
    {
        return AuditChain::verify(
            $this->client->auditLogs->events->listAll(['organization_id' => $organizationId, 'order' => 'asc']),
            $previousHash,
        );
    }
}
