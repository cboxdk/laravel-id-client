<?php

declare(strict_types=1);

use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Workspace\Schemas\TeamInvitationReceipt;
use Cbox\Id\Client\Management\WorkspaceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('preserves multiple invitation roles and the explicitly selected workspace across invite and resend', function (): void {
    Http::fake(['*' => envelope([
        'id' => 'invite_1', 'email' => 'reader@example.test',
        'roles' => ['viewer', 'billing'], 'status' => 'invited',
    ])]);
    $client = new WorkspaceClient(baseUrl: 'https://id.example.test', accessToken: 'human_token');
    $options = new CallOptions(headers: ['Cbox-Organization' => 'org_selected']);

    $invited = $client->team->invite(['email' => 'reader@example.test', 'roles' => ['viewer', 'billing']], $options);
    $resent = $client->team->invitations->resend('invite_1', $options);

    expect($invited->data)->toBeInstanceOf(TeamInvitationReceipt::class)
        ->and($invited->data->roles)->toBe(['viewer', 'billing'])
        ->and($resent->data->roles)->toBe(['viewer', 'billing']);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/workspace/members')
        && $request->data()['roles'] === ['viewer', 'billing']
        && $request->hasHeader('Cbox-Organization', 'org_selected'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/workspace/invitations/invite_1/resend')
        && $request->hasHeader('Cbox-Organization', 'org_selected'));
});

it('keeps role and environment versions separate when updating selected row access', function (): void {
    Http::fake(['*' => envelope([
        'id' => 'member_1', 'email' => null, 'name' => null, 'role' => 'member',
        'roles' => ['viewer'], 'roles_version' => 'roles_current', 'status' => 'active',
        'all_environments' => false, 'environment_ids' => ['env_selected'],
        'environment_access_version' => 'rows_next',
    ])]);
    $client = new WorkspaceClient(baseUrl: 'https://id.example.test', accessToken: 'human_token', headers: ['Cbox-Organization' => 'org_selected']);

    $result = $client->team->environmentAccess('member_1', [
        'environment_access_version' => 'rows_current',
        'all_environments' => false, 'environment_ids' => ['env_selected'],
    ]);

    expect($result->data->email)->toBeNull()
        ->and($result->data->rolesVersion)->toBe('roles_current')
        ->and($result->data->environmentAccessVersion)->toBe('rows_next')
        ->and($result->data->environmentIds)->toBe(['env_selected']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->hasHeader('Cbox-Organization', 'org_selected')
        && $request->data()['environment_access_version'] === 'rows_current'
        && ! array_key_exists('roles_version', $request->data()));
});

it('sends only the reviewed app-role version when removing native access from an exact workspace', function (): void {
    Http::fake(['*' => Http::response(null, 204)]);
    $client = new WorkspaceClient(baseUrl: 'https://id.example.test', accessToken: 'human_token');
    $client->team->remove('member_1', ['roles_version' => 'roles_reviewed'], new CallOptions(headers: ['Cbox-Organization' => 'org_selected']));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://id.example.test/api/v1/workspace/members/member_1'
        && $request->hasHeader('Cbox-Organization', 'org_selected')
        && $request->data() === ['roles_version' => 'roles_reviewed']);
});
