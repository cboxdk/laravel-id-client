<?php

declare(strict_types=1);

use Cbox\Id\Client\Facades\CboxIdWebhooks;
use Cbox\Id\Client\Webhooks\EventType;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use Illuminate\Testing\TestResponse;

/**
 * Event names as constants, and the delivery `sequence` that lets a receiver notice a
 * gap or put retried deliveries back in order.
 */
beforeEach(function (): void {
    config(['cbox-id-client.webhooks.secret' => 'whsec_test']);
});

function deliverSigned(array $envelope): TestResponse
{
    $body = (string) json_encode($envelope);
    $ts = time();

    return test()->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$body, 'whsec_test'),
        'HTTP_X_CBOX_TIMESTAMP' => (string) $ts,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('registers a handler against an event constant and exposes the sequence', function (): void {
    $received = null;
    CboxIdWebhooks::on(EventType::MembershipCreated, function (WebhookEvent $event) use (&$received): void {
        $received = $event;
    });

    deliverSigned(['type' => 'membership.created', 'sequence' => 42, 'data' => ['organization_id' => 'org_1', 'user_id' => 'user_1'], 'delivery_id' => 'wd_1'])
        ->assertOk()->assertJson(['queued' => true]);

    expect($received?->sequence)->toBe(42)
        ->and($received?->is(EventType::MembershipCreated))->toBeTrue()
        ->and($received?->eventType())->toBe(EventType::MembershipCreated);
});

it('leaves the sequence null when the instance does not send one', function (): void {
    $received = null;
    CboxIdWebhooks::on('*', function (WebhookEvent $event) use (&$received): void {
        $received = $event;
    });

    deliverSigned(['type' => 'something.custom', 'data' => []])->assertOk();

    expect($received?->sequence)->toBeNull()->and($received?->eventType())->toBeNull();
});

it('names every event in the shared contract', function (): void {
    $names = array_map(fn (EventType $t): string => $t->value, EventType::cases());

    foreach ([
        'membership.created', 'membership.updated', 'membership.deleted',
        'invitation.created', 'invitation.accepted', 'invitation.revoked',
        'organization.updated', 'organization.deleted',
        'api_key.created', 'api_key.revoked', 'support_session.started',
        'user.login', 'identity.linked', 'role.unassigned', 'role.assigned_everywhere',
    ] as $name) {
        expect($names)->toContain($name);
    }
});
