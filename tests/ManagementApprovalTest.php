<?php

declare(strict_types=1);

use Cbox\Id\Client\Exceptions\ApprovalDenied;
use Cbox\Id\Client\Exceptions\ApprovalException;
use Cbox\Id\Client\Exceptions\ApprovalExpired;
use Cbox\Id\Client\Exceptions\UnexpectedResponse;
use Cbox\Id\Client\Management\Environment\Schemas\AppSecret;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\Danger;
use Cbox\Id\Client\Management\Transport\PendingApproval;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
 * A write a key's policy holds for a person's approval: `202 approval_required`, the
 * person approves on their device, and the SAME request — same body, same Idempotency-Key —
 * goes again with `Cbox-Approval`. Waited on by default; returned on request.
 */

it('waits for the approval, then repeats the request with it and the same key', function (): void {
    $seen = fakeManagement(
        approvalRequired(headers: ['Retry-After' => '5']),
        approvalStatus('pending'),
        approvalStatus('approved'),
        envelope(['id' => 'sec_2', 'client_secret' => 'csec_new'], 201),
    );
    $told = [];

    $response = envClient(onApprovalRequired: function (PendingApproval $approval, ApprovalContext $context) use (&$told): void {
        $told[] = [$approval->bindingCode, $context->action, $context->danger];
    })->apps->secrets->rotate('app_1', ['grace_seconds' => 3600]);

    expect($told)->toBe([['42-17', 'apps.secrets.rotate', Danger::Critical]]);

    [$first, $poll1, $poll2, $repeat] = $seen->getArrayCopy();
    expect($poll1->method())->toBe('GET')
        ->and($poll1->url())->toBe('https://acme.test/api/v1/action-approvals/apr_1')
        ->and($poll1->header('Authorization'))->toBe(['Bearer cbid_env_test'])
        ->and($poll2->url())->toBe($poll1->url())
        ->and($repeat->method())->toBe('POST')
        ->and($repeat->header('Cbox-Approval'))->toBe(['apr_1'])
        ->and($repeat->header('Idempotency-Key'))->toBe($first->header('Idempotency-Key'))
        ->and($repeat->data())->toBe(['grace_seconds' => 3600])
        ->and($first->hasHeader('Cbox-Approval'))->toBeFalse();

    expect($response)->toBeInstanceOf(ApiResponse::class)
        ->and($response->data)->toBeInstanceOf(AppSecret::class)
        ->and($response->data->clientSecret)->toBe('csec_new');

    // The 202's Retry-After, then the default poll interval.
    Sleep::assertSequence([Sleep::usleep(5_000_000), Sleep::usleep(2_000_000)]);
});

it('throws when the person denies it, or nobody answers in time', function (string $status, string $exception): void {
    $seen = fakeManagement(approvalRequired(), approvalStatus($status));

    expect(fn () => envClient()->apps->secrets->rotate('app_1', ['grace_seconds' => 0]))->toThrow($exception);
    expect($seen)->toHaveCount(2);
})->with([
    'denied' => ['denied', ApprovalDenied::class],
    'expired' => ['expired', ApprovalExpired::class],
]);

it('gives up when an approval is never answered past its expiry', function (): void {
    $seen = fakeManagement(
        Http::response(['error' => 'approval_required', 'approval' => [
            'id' => 'apr_1', 'binding_code' => '1', 'expires_at' => now()->addSeconds(1)->toIso8601String(),
        ]], 202),
        approvalStatus('pending'),
    );
    Sleep::fake(syncWithCarbon: true);

    expect(fn () => envClient(approvalPollIntervalMs: 40_000)->apps->delete('app_1'))->toThrow(ApprovalExpired::class);
    expect($seen)->toHaveCount(2);
});

it('never polls another origin with the credential', function (): void {
    $seen = fakeManagement(approvalRequired(pollUrl: 'https://evil.test/api/v1/action-approvals/apr_1'));

    expect(fn () => envClient()->apps->delete('app_1'))->toThrow(UnexpectedResponse::class, 'another origin (https://evil.test)');
    expect($seen)->toHaveCount(1);
});

it('polls a relative poll URL on its own host', function (): void {
    $seen = fakeManagement(approvalRequired(pollUrl: '/api/v1/action-approvals/apr_1'), approvalStatus('approved'), Http::response(null, 204));

    envClient()->apps->delete('app_1');

    expect($seen[1]->url())->toBe('https://acme.test/api/v1/action-approvals/apr_1');
});

it('returns the pending approval when asked, and resume() finishes the call', function (): void {
    $seen = fakeManagement(
        approvalRequired(),
        approvalStatus('approved'),
        envelope(['id' => 'sec_2', 'client_secret' => 'csec_new'], 201),
    );
    $told = 0;

    $outcome = envClient(onApprovalRequired: function () use (&$told): void {
        $told++;
    })->apps->secrets->rotate('app_1', ['grace_seconds' => 0], CallOptions::returnPendingApproval(idempotencyKey: 'k-later'));

    expect($outcome)->toBeInstanceOf(PendingApprovalResult::class)
        ->and($outcome->approval->bindingCode)->toBe('42-17')
        ->and($outcome->idempotencyKey)->toBe('k-later')
        ->and($outcome->action)->toBe('apps.secrets.rotate')
        ->and($seen)->toHaveCount(1);

    $response = $outcome->resume();

    expect($response->data->clientSecret)->toBe('csec_new')
        ->and($seen[2]->header('Idempotency-Key'))->toBe(['k-later'])
        ->and($seen[2]->header('Cbox-Approval'))->toBe(['apr_1'])
        ->and($told)->toBe(0);
});

it('answers normally in return mode when nothing is held', function (): void {
    fakeManagement(envelope(['id' => 'sec_2'], 201));

    $outcome = envClient()->apps->secrets->rotate('app_1', ['grace_seconds' => 0], CallOptions::returnPendingApproval());

    expect($outcome)->toBeInstanceOf(ApiResponse::class);
});

it('stops after being held again and again', function (): void {
    fakeManagement(
        approvalRequired('apr_1'), approvalStatus('approved', 'apr_1'),
        approvalRequired('apr_2'), approvalStatus('approved', 'apr_2'),
        approvalRequired('apr_3'), approvalStatus('approved', 'apr_3'),
        approvalRequired('apr_4'),
    );

    expect(fn () => envClient()->apps->delete('app_1'))->toThrow(function (ApprovalException $e): void {
        expect($e->reason)->toBe('consumed')->and($e->approval->id)->toBe('apr_4');
    });
});
