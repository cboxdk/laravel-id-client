<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\ValueObjects\VerifiedToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The work needs a tenant and the caller acts for none.
 *
 * A client-credentials token, or a person who signed in without choosing an organization,
 * carries no `org`. A multi-tenant application that reads that as "any organization" has
 * built a cross-tenant hole, so {@see VerifiedToken::organizationOrFail()}
 * refuses — and it used to refuse with a bare `RuntimeException`, which every application
 * rendered as a 500. It is the caller's situation, not a server fault: 403, with a reason.
 */
class OrganizationRequired extends CboxIdException implements HttpExceptionInterface
{
    public static function forToken(): self
    {
        return new self('This token acts for no organization; it cannot be used for tenant-scoped work.');
    }

    public static function forPrincipal(): self
    {
        return new self('Choose an organization before continuing; this action is tenant-scoped.');
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return [];
    }

    public function render(Request $request): JsonResponse|false
    {
        if (! $request->expectsJson()) {
            return false;
        }

        return new JsonResponse(['error' => [
            'type' => 'organization_required',
            'detail' => $this->getMessage(),
        ]], 403);
    }
}
