<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\AuditLogs;

use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Closure;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Buffers audit events and sends them in batches of up to 100 — one
 * `POST /audit-logs/events` per batch, each under its own `Idempotency-Key`.
 *
 *     $audit = new AuditLogger($env);
 *     $audit->record([
 *         'organization_id' => $org->id,
 *         'action' => 'invoice.voided',
 *         'actor' => ['id' => $user->id, 'type' => 'user', 'name' => $user->name],
 *         'targets' => [['id' => $invoice->id, 'type' => 'invoice']],
 *     ]);
 *     $audit->flush();   // or close(); Laravel's binding flushes when the app terminates
 *
 * A batch is sent when it is full (from inside `record()`), and on `flush()` / `close()`.
 * Batches go out one at a time and in order, because the server appends each to its
 * organization's hash chain in the order received. A batch that fails stays at the head of
 * the queue with the SAME key, so sending it again can never record an event twice.
 *
 * A failure while `record()` sends a full batch does not throw — recording an audit event
 * should not break the request that caused it. It goes to `$onError` (when given) and the
 * batch waits for the next send; `flush()` and `close()` do throw.
 *
 * @phpstan-type AuditEventInput array{organization_id: string, action: string, occurred_at?: string, actor: array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}, targets?: list<array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}>, context?: array{location?: string|null, user_agent?: string|null}, metadata?: array<string, mixed>}
 * @phpstan-type AuditEvent array{organization_id: string, action: string, occurred_at: string, actor: array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}, targets?: list<array{id: string, type: string, name?: string|null, metadata?: array<string, mixed>}>, context?: array{location?: string|null, user_agent?: string|null}, metadata?: array<string, mixed>}
 */
class AuditLogger
{
    /** The most events one `POST /audit-logs/events` takes. */
    public const MAX_BATCH = 100;

    /** @var list<AuditEvent> */
    private array $buffer = [];

    /** @var list<array{events: list<AuditEvent>, key: string}> */
    private array $queue = [];

    private bool $closed = false;

    /**
     * @param  int  $batchSize  events per request, 1–100
     * @param  (Closure(Throwable, list<AuditEvent>): void)|null  $onError  told when a send from `record()` fails, with the batch that stays queued
     */
    public function __construct(
        private readonly EnvironmentClient $client,
        private readonly int $batchSize = self::MAX_BATCH,
        private readonly ?Closure $onError = null,
    ) {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH) {
            throw new InvalidArgumentException('batchSize must be 1–'.self::MAX_BATCH.'.');
        }
    }

    /**
     * Buffer one event; `occurred_at` defaults to now (UTC, to the millisecond). A full
     * batch is sent at once.
     *
     * @param  AuditEventInput  $event
     */
    public function record(array $event): void
    {
        if ($this->closed) {
            throw new LogicException('This AuditLogger is closed.');
        }

        $event['occurred_at'] ??= Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $this->buffer[] = $event;

        if (count($this->buffer) < $this->batchSize) {
            return;
        }

        try {
            $this->flush();
        } catch (Throwable $e) {
            if ($this->onError !== null) {
                ($this->onError)($e, $this->queue[0]['events'] ?? []);
            }
        }
    }

    /** Events buffered or queued, not yet acknowledged by the server. */
    public function pending(): int
    {
        return count($this->buffer) + array_sum(array_map(static fn (array $batch): int => count($batch['events']), $this->queue));
    }

    /** Send everything buffered, and return once the server has it. Throws if a batch fails. */
    public function flush(): void
    {
        while ($this->buffer !== []) {
            $this->queue[] = ['events' => array_splice($this->buffer, 0, $this->batchSize), 'key' => ManagementTransport::uuid()];
        }

        while (($batch = $this->queue[0] ?? null) !== null) {
            $this->client->auditLogs->events->create(['events' => $batch['events']], new CallOptions(idempotencyKey: $batch['key']));
            array_shift($this->queue);
        }
    }

    /** Flush, and refuse further events. */
    public function close(): void
    {
        $this->closed = true;
        $this->flush();
    }
}
