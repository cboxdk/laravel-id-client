<?php

declare(strict_types=1);

use Cbox\Id\Client\Contracts\KeptPermissionDecisions;
use Cbox\Id\Client\Decisions\KeptDecisions;
use Cbox\Id\Client\Decisions\RefreshKeptDecision;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

use function Illuminate\Support\defer;

/*
 * A kept answer serves page views for weeks, refreshed in the background. What ends a
 * kept "yes" early is Cbox ID saying so: a webhook that takes access away, or a
 * back-channel logout, makes the next check ask again.
 */

beforeEach(function (): void {
    config([
        'cbox-id-client.issuer' => 'https://id.test',
        'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1',
        'cbox-id-client.redirect' => 'https://app.test/callback',
        'cbox-id-client.webhooks.secret' => 'whsec_test',
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    $this->allowed = true;
    $allowed = &$this->allowed;

    Http::fake(['*/oauth/decisions' => static function (ClientRequest $request) use (&$allowed) {
        return Http::response([
            'mode' => 'rbac', 'subject' => ['type' => 'user', 'id' => $request['subject']],
            'organization' => $request['org'], 'client_id' => 'client_1',
            'org_role' => 'member', 'organization_active' => true, 'allowed' => $allowed,
            'results' => [['permission' => 'invoices:approve', 'allowed' => $allowed]],
        ]);
    }]);
    fakeCbox();
});

function revocationCheck(string $subject = 'user-1', ?string $organization = 'org-1'): bool
{
    app()->instance('request', Request::create('https://app.test/invoices', 'GET'));

    return app(KeptPermissionDecisions::class)->check($subject, $organization, ['invoices:approve'])->allowed();
}

function revocationAsks(): int
{
    return Http::recorded(static fn (ClientRequest $request): bool => str_ends_with($request->url(), '/oauth/decisions'))->count();
}

/** @param array<string, mixed> $data */
function deliverRevocation(string $type, array $data): TestResponse
{
    $body = (string) json_encode(['type' => $type, 'data' => $data, 'delivery_id' => 'wd_'.bin2hex(random_bytes(4))]);
    $ts = time();

    return test()->call('POST', '/cbox-id/webhooks', [], [], [], [
        'HTTP_X_CBOX_SIGNATURE' => "t={$ts},v1=".hash_hmac('sha256', $ts.'.'.$body, 'whsec_test'),
        'HTTP_X_CBOX_TIMESTAMP' => (string) $ts,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('serves a page view from an answer three weeks old and refreshes it in the background', function (): void {
    Queue::fake();
    revocationCheck();
    $this->allowed = false;
    $this->travel(21)->days();

    expect(revocationCheck())->toBeTrue()
        ->and(revocationAsks())->toBe(1);
    Queue::assertPushed(RefreshKeptDecision::class);
});

it('asks now once the answer is older than thirty days', function (): void {
    revocationCheck();
    $this->allowed = false;
    $this->travel(31)->days();

    expect(revocationCheck())->toBeFalse();
});

it('refreshes after the response, not in the request, when the app has no queue worker', function (): void {
    config(['queue.default' => 'sync']);
    revocationCheck();
    $this->allowed = false;
    $this->travel(31)->seconds();

    // The page view: the kept answer and no call.
    expect(revocationCheck())->toBeTrue()
        ->and(revocationAsks())->toBe(1);

    defer()->invoke();

    expect(revocationAsks())->toBe(2)
        ->and(revocationCheck())->toBeFalse();
});

it('drops a kept yes when Cbox ID says the person lost access', function (string $type, array $data): void {
    revocationCheck();
    revocationCheck('user-2');
    $this->allowed = false;

    deliverRevocation($type, $data)->assertOk()->assertJson(['queued' => true]);

    // Still inside fresh_for: without the webhook this would be the kept yes.
    expect(revocationCheck())->toBeFalse()
        // Somebody else's answer is untouched.
        ->and(revocationCheck('user-2'))->toBeTrue()
        ->and(revocationAsks())->toBe(3);
})->with([
    'user.deactivated' => ['user.deactivated', ['user_id' => 'user-1']],
    'id.user.deactivated' => ['id.user.deactivated', ['user_id' => 'user-1']],
    'organization.member_removed' => ['organization.member_removed', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
    'organization.member_role_changed' => ['organization.member_role_changed', ['user_id' => 'user-1', 'organization_id' => 'org-1', 'role' => 'member']],
    'membership.deleted' => ['membership.deleted', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
    'membership.updated' => ['membership.updated', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
    'role.unassigned' => ['role.unassigned', ['user_id' => 'user-1', 'role_id' => 'role_1', 'organization_id' => 'org-1']],
    'role.unassigned_everywhere' => ['role.unassigned_everywhere', ['user_id' => 'user-1', 'role_id' => 'role_1']],
    'role.assigned' => ['role.assigned', ['user_id' => 'user-1', 'role_id' => 'role_1', 'organization_id' => 'org-1']],
    'governance.access.revoked' => ['governance.access.revoked', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
    'directory.user.deprovisioned' => ['directory.user.deprovisioned', ['user_id' => 'user-1', 'organization_id' => 'org-1']],
]);

it('reads a prefixed id in the webhook as the subject the token carries', function (): void {
    revocationCheck('01j9z3x5k8m2n4p6q7r8s9t0vw', '01j9z3x5k8m2n4p6q7r8s9t0vx');
    $this->allowed = false;

    deliverRevocation('id.organization.member_removed', [
        'user_id' => 'usr_01J9Z3X5K8M2N4P6Q7R8S9T0VW', 'organization_id' => 'org_01J9Z3X5K8M2N4P6Q7R8S9T0VX',
    ])->assertOk();

    expect(revocationCheck('01j9z3x5k8m2n4p6q7r8s9t0vw', '01j9z3x5k8m2n4p6q7r8s9t0vx'))->toBeFalse();
});

it('drops every kept answer of an organization that was suspended, archived or deleted', function (string $type): void {
    revocationCheck();
    revocationCheck('user-2');
    revocationCheck('user-1', 'org-2');
    $this->allowed = false;

    deliverRevocation($type, ['id' => 'org-1', 'status' => 'suspended'])->assertOk();

    expect(revocationCheck())->toBeFalse()
        ->and(revocationCheck('user-2'))->toBeFalse()
        ->and(revocationCheck('user-1', 'org-2'))->toBeTrue();
})->with(['organization.suspended', 'organization.archived', 'organization.deleted']);

it('keeps an answer asked after the revocation', function (): void {
    revocationCheck();
    deliverRevocation('user.deactivated', ['user_id' => 'user-1']);
    $this->travel(1)->seconds();
    revocationCheck();
    revocationCheck();

    expect(revocationAsks())->toBe(2);
});

it('drops the kept answers of a person Cbox ID signed out over the back channel', function (): void {
    config(['cbox-id-client.backchannel_logout.enabled' => true]);
    $this->configBeforeBoot = ['cbox-id-client.backchannel_logout.enabled' => true, 'cbox-id-client.webhooks.secret' => 'whsec_test'];
    $this->refreshApplication();
    config([
        'cbox-id-client.issuer' => 'https://id.test', 'cbox-id-client.client_id' => 'client_1',
        'cbox-id-client.client_secret' => 'secret_1', 'cbox-id-client.redirect' => 'https://app.test/callback',
    ]);
    $allowed = true;
    Http::fake(['*/oauth/decisions' => static function () use (&$allowed) {
        return Http::response([
            'mode' => 'rbac', 'subject' => ['type' => 'user', 'id' => 'user-1'], 'organization' => 'org-1', 'client_id' => 'client_1',
            'org_role' => 'member', 'organization_active' => true, 'allowed' => $allowed,
            'results' => [['permission' => 'invoices:approve', 'allowed' => $allowed]],
        ]);
    }]);
    fakeCbox();
    revocationCheck();
    $allowed = false;

    $token = JWT::encode([
        'iss' => 'https://id.test', 'aud' => 'client_1', 'sub' => 'user-1', 'sid' => 'sid-A', 'iat' => time(), 'exp' => time() + 120,
        'jti' => 'lt_1', 'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass],
    ], rsaKeypair()['private'], 'RS256', 'test-1', ['typ' => 'logout+jwt']);

    $this->post('/cbox-id/backchannel-logout', ['logout_token' => $token])->assertOk();

    expect(revocationCheck())->toBeFalse();
});

it('can be told directly that a person or an organization lost access', function (): void {
    revocationCheck();
    revocationCheck('user-2', 'org-2');
    $this->allowed = false;

    app(KeptDecisions::class)->revokeSubject('user-1');
    app(KeptDecisions::class)->revokeOrganization('org-2');

    expect(revocationCheck())->toBeFalse()
        ->and(revocationCheck('user-2', 'org-2'))->toBeFalse();
});
