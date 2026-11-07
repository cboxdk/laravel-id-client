<?php

declare(strict_types=1);

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ResourceNotFound;
use Cbox\Id\Client\Management\Data\Invitation;
use Cbox\Id\Client\Management\HttpManagementClient;
use Cbox\Id\Client\Testing\FakeManagement;
use Illuminate\Support\Facades\Http;

it('reads a completed invitation receipt from the exact organization URL', function (): void {
    Http::fake(['*' => Http::response(['data' => [
        'id' => 'inv_1', 'email' => 'person@example.test', 'organization_id' => 'org_1', 'client_id' => 'app_1',
        'status' => 'accepted', 'accepted_by' => 'usr_1', 'accepted_at' => '2026-11-06T12:00:00Z',
    ]])]);
    $receipt = (new HttpManagementClient('https://id.example.test/api/v1', 'secret'))->invitation('org_1', 'inv_1');
    expect($receipt->acceptedBy)->toBe('usr_1')->and($receipt->acceptedAt?->format('Y-m-d'))->toBe('2026-11-06')
        ->and($receipt->clientId)->toBe('app_1')->and($receipt->organizationId)->toBe('org_1');
    Http::assertSent(fn ($request): bool => $request->method() === 'GET' && $request->url() === 'https://id.example.test/api/v1/organizations/org_1/invitations/inv_1');
});

it('does not invent acceptance for historical or pending invitations', function (): void {
    $receipt = Invitation::fromArray(['id' => 'inv_1', 'email' => 'person@example.test', 'status' => 'accepted']);
    expect($receipt->acceptedBy)->toBeNull()->and($receipt->acceptedAt)->toBeNull();
});

it('keeps fake acceptance receipts scoped to their organization', function (): void {
    $fake = new FakeManagement;
    $fake->withInvitation(new Invitation('inv_1', 'person@example.test', OrganizationRole::Member, organizationId: 'org_1', status: 'accepted', acceptedBy: 'usr_1', acceptedAt: new DateTimeImmutable));
    expect($fake->invitation('org_1', 'inv_1')->acceptedBy)->toBe('usr_1');
    expect(fn () => $fake->invitation('org_other', 'inv_1'))->toThrow(ResourceNotFound::class);
});
