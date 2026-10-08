<?php

declare(strict_types=1);

use Cbox\Id\Client\Events\ManagementApprovalRequired;
use Cbox\Id\Client\Exceptions\NotConfigured;
use Cbox\Id\Client\Facades\CboxIdApi;
use Cbox\Id\Client\Management\AuditLogs\AuditLogger;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\ManagementClients;
use Cbox\Id\Client\Management\WorkspaceClient;
use Illuminate\Support\Facades\Event;

/*
 * The typed clients as a Laravel app gets them: from config, through `CboxIdApi` or the
 * container, with approvals surfacing as an event and buffered audit events flushed when
 * the application terminates.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://acme.test',
        'cbox-id-client.management.key' => 'cbid_env_from_config',
        'cbox-id-client.management.workspace_key' => 'cbid_ws_from_config',
        'cbox-id-client.management.root_url' => 'https://root.test',
    ]);
    $this->app->forgetInstance(ManagementClients::class);
});

it('builds the environment client from config, on the issuer\'s host', function (): void {
    $seen = fakeManagement(envelope(appData()));

    CboxIdApi::environment()->apps->get('app_1');

    expect($seen[0]->url())->toBe('https://acme.test/api/v1/apps/app_1')
        ->and($seen[0]->header('Authorization'))->toBe(['Bearer cbid_env_from_config'])
        ->and(app(EnvironmentClient::class))->toBe(CboxIdApi::environment());
});

it('prefers management.url, the same setting the older client reads', function (): void {
    config(['cbox-id-client.management.url' => 'https://ids.acme.test/api/v1']);

    expect(app(EnvironmentClient::class)->transport->baseUrl)->toBe('https://ids.acme.test/api/v1');
});

it('builds the workspace client on the platform root', function (): void {
    expect(app(WorkspaceClient::class)->transport->baseUrl)->toBe('https://root.test/api/v1');
});

it('names the missing key instead of failing somewhere else', function (): void {
    config(['cbox-id-client.management.key' => null, 'cbox-id-client.management.workspace_key' => '']);

    expect(fn () => CboxIdApi::environment())->toThrow(NotConfigured::class, 'CBOX_ID_MANAGEMENT_KEY')
        ->and(fn () => CboxIdApi::workspace())->toThrow(NotConfigured::class, 'CBOX_ID_WORKSPACE_KEY');
});

it('acts as a person in any environment from the root host', function (): void {
    $seen = fakeManagement(envelope(appData()));

    CboxIdApi::environmentAs('root_token', environment: 'acme-staging')->apps->get('app_1');

    expect($seen[0]->url())->toBe('https://root.test/api/v1/apps/app_1')
        ->and($seen[0]->header('Cbox-Environment'))->toBe(['acme-staging'])
        ->and($seen[0]->header('Authorization'))->toBe(['Bearer root_token']);
});

it('announces a held call as an event', function (): void {
    Event::fake([ManagementApprovalRequired::class]);
    fakeManagement(approvalRequired(), approvalStatus('approved'), envelope(['id' => 'sec_1'], 201));

    CboxIdApi::environment()->apps->secrets->rotate('app_1', ['grace_seconds' => 0]);

    Event::assertDispatched(ManagementApprovalRequired::class, fn (ManagementApprovalRequired $e): bool => $e->approval->bindingCode === '42-17'
        && $e->context->action === 'apps.secrets.rotate');
});

it('flushes buffered audit events when the application terminates', function (): void {
    $seen = fakeManagement(envelope(['events' => []], 201));

    app(AuditLogger::class)->record(['organization_id' => 'org_1', 'action' => 'invoice.voided', 'actor' => ['id' => 'usr_1', 'type' => 'user']]);
    expect($seen)->toHaveCount(0);

    $this->app->terminate();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->url())->toBe('https://acme.test/api/v1/audit-logs/events')
        ->and(CboxIdApi::auditLogger()->pending())->toBe(0);
});
