<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\AuditLogExportFailed;
use Cbox\Id\Client\Exceptions\CboxIdApiException;
use Cbox\Id\Client\Management\AuditLogs\AuditChain;
use Cbox\Id\Client\Management\AuditLogs\AuditLogger;
use Cbox\Id\Client\Management\AuditLogs\AuditLogs;
use Cbox\Id\Client\Management\AuditLogs\CanonicalJson;
use Cbox\Id\Client\Management\Environment\Schemas\AuditLogEvent;
use Illuminate\Support\Facades\Http;

/*
 * Audit Logs: the buffered sender, the export helper, and the chain verifier — the last
 * proven against tests/Fixtures/audit-chain.json, the same vector id-js uses: events as
 * cbox-id serves them and the exact string its own PHP hashed, so "byte-compatible with
 * the server" is a fact here, not a claim.
 */

function auditFixture(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/Fixtures/audit-chain.json'), true, 512, JSON_THROW_ON_ERROR)['data'];
}

function auditEvent(int $n): array
{
    return [
        'organization_id' => 'org_1',
        'action' => 'invoice.voided',
        'actor' => ['id' => "usr_{$n}", 'type' => 'user'],
    ];
}

it('writes the canonical JSON the server hashed, byte for byte', function (): void {
    foreach (auditFixture() as $event) {
        expect(CanonicalJson::encode(AuditChain::document($event)))->toBe($event['canonical'])
            ->and(AuditChain::hash($event['prev_hash'], $event))->toBe($event['hash'])
            ->and(AuditChain::hash($event['prev_hash'], AuditLogEvent::fromArray($event)))->toBe($event['hash']);
    }
});

it('verifies the fixture chain, typed or raw, in any order', function (): void {
    $events = auditFixture();
    $typed = array_map(AuditLogEvent::fromArray(...), $events);

    foreach ([$events, array_reverse($typed)] as $input) {
        $result = AuditChain::verify($input);

        expect($result->valid)->toBeTrue()
            ->and($result->verifiedCount)->toBe(3)
            ->and($result->firstSequence)->toBe(1)
            ->and($result->lastSequence)->toBe(3)
            ->and($result->brokenAtSequence)->toBeNull()
            ->and($result->reason)->toBeNull();
    }
});

it('finds where a chain breaks, and how', function (Closure $tamper, string $reason, int $at): void {
    $result = AuditChain::verify($tamper(auditFixture()));

    expect($result->valid)->toBeFalse()
        ->and($result->reason)->toBe($reason)
        ->and($result->brokenAtSequence)->toBe($at);
})->with([
    'an edited event' => [function (array $events): array {
        $events[1]['metadata'] = ['changed' => true];

        return $events;
    }, 'hash', 2],
    'a removed event' => [function (array $events): array {
        unset($events[1]);

        return array_values($events);
    }, 'missing', 2],
    'a relinked event' => [function (array $events): array {
        $events[2]['prev_hash'] = str_repeat('a', 64);

        return $events;
    }, 'link', 3],
]);

it('checks a chain that starts mid-way from the hash you kept', function (): void {
    $events = array_slice(auditFixture(), 1);

    expect(AuditChain::verify($events)->valid)->toBeTrue()
        ->and(AuditChain::verify($events, AuditChain::GENESIS)->reason)->toBe('link')
        ->and(AuditChain::verify($events, auditFixture()[0]['hash'])->valid)->toBeTrue();
});

it('verifies an organization\'s chain through the API, oldest first', function (): void {
    $seen = fakeManagement(envelope(auditFixture(), meta: ['has_more' => false]));

    $result = (new AuditLogs(envClient()))->verifyChain('org_1');

    expect($result->valid)->toBeTrue()
        ->and($seen[0]->url())->toBe('https://acme.test/api/v1/audit-logs/events?organization_id=org_1&order=asc');
});

it('sends events in batches of up to 100, each under its own key', function (): void {
    $batch = fn () => envelope(['events' => []], 201);
    $seen = fakeManagement($batch(), $batch(), $batch());
    $logger = new AuditLogger(envClient());

    for ($i = 0; $i < 250; $i++) {
        $logger->record(auditEvent($i));
    }

    // Two full batches went as they filled; the rest waits for flush().
    expect($seen)->toHaveCount(2)->and($logger->pending())->toBe(50);

    $logger->flush();

    expect($seen)->toHaveCount(3)->and($logger->pending())->toBe(0);
    $sizes = array_map(fn ($r) => count($r->data()['events']), $seen->getArrayCopy());
    $keys = array_map(fn ($r) => $r->header('Idempotency-Key')[0], $seen->getArrayCopy());
    expect($sizes)->toBe([100, 100, 50])
        ->and(array_unique($keys))->toHaveCount(3)
        ->and($seen[0]->data()['events'][0]['occurred_at'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/')
        ->and($seen[0]->data()['events'][0]['actor'])->toBe(['id' => 'usr_0', 'type' => 'user']);
});

it('keeps a failed batch queued under the same key, so it is never recorded twice', function (): void {
    $seen = fakeManagement(
        Http::response(['error' => 'server_error', 'message' => 'Down'], 503),
        envelope(['events' => []], 201),
    );
    $errors = [];
    $logger = new AuditLogger(envClient(maxRetries: 0), batchSize: 2, onError: function (Throwable $e, array $batch) use (&$errors): void {
        $errors[] = [$e::class, count($batch)];
    });

    $logger->record(auditEvent(1));
    $logger->record(auditEvent(2)); // full: sent, fails, reported — not thrown

    expect($errors)->toBe([[CboxIdApiException::class, 2]])
        ->and($logger->pending())->toBe(2);

    $logger->close();

    expect($seen)->toHaveCount(2)
        ->and($seen[1]->header('Idempotency-Key'))->toBe($seen[0]->header('Idempotency-Key'))
        ->and($logger->pending())->toBe(0)
        ->and(fn () => $logger->record(auditEvent(3)))->toThrow(LogicException::class);
});

it('refuses a batch size the API does not take', function (): void {
    expect(fn () => new AuditLogger(envClient(), batchSize: 101))->toThrow(InvalidArgumentException::class);
});

function auditExport(string $state, ?string $url = null): array
{
    return ['id' => 'exp_1', 'organization_id' => 'org_1', 'state' => $state, 'filters' => [], 'row_count' => $url ? 3 : null, 'url' => $url];
}

it('waits for an export to be ready', function (): void {
    $seen = fakeManagement(
        envelope(auditExport('pending'), 201),
        envelope(auditExport('pending')),
        envelope(auditExport('ready', 'https://files.acme.test/exp_1.csv')),
    );

    $export = (new AuditLogs(envClient()))->export(['organization_id' => 'org_1'], idempotencyKey: 'k-export');

    expect($export->url)->toBe('https://files.acme.test/exp_1.csv')
        ->and($export->rowCount)->toBe(3)
        ->and($seen[0]->header('Idempotency-Key'))->toBe(['k-export'])
        ->and($seen[2]->url())->toBe('https://acme.test/api/v1/audit-logs/exports/exp_1');
});

it('throws when an export fails', function (): void {
    fakeManagement(envelope(auditExport('pending'), 201), envelope(auditExport('failed')));

    expect(fn () => (new AuditLogs(envClient()))->export())->toThrow(function (AuditLogExportFailed $e): void {
        expect($e->getMessage())->toBe('Audit log export exp_1 is failed.')->and($e->export?->state)->toBe('failed');
    });
});
