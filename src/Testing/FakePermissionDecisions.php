<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Authz\PermissionDecision;
use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use SensitiveParameter;

class FakePermissionDecisions implements PermissionDecisions
{
    /** @var list<PermissionDecision|AuthenticationFailed> */
    private array $outcomes = [];

    /** @var list<array{token_hash: string, subject: string, organization: string|null, permissions: list<string>}> */
    public array $calls = [];

    public function queue(PermissionDecision|AuthenticationFailed $outcome): void
    {
        $this->outcomes[] = $outcome;
    }

    public function check(#[SensitiveParameter] string $accessToken, string $subject, ?string $organization, array $permissions): PermissionDecision
    {
        $this->calls[] = ['token_hash' => hash('sha256', $accessToken), 'subject' => $subject, 'organization' => $organization, 'permissions' => $permissions];
        $outcome = array_shift($this->outcomes) ?? new PermissionDecision;
        if ($outcome instanceof AuthenticationFailed) {
            throw $outcome;
        }

        return $outcome;
    }
}
