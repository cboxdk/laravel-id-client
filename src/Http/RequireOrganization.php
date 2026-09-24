<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Http;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\ClientConfigurationException;
use Cbox\Id\Client\Exceptions\OrganizationRequired;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Tenancy\CurrentPrincipal;
use Cbox\Id\Client\ValueObjects\Identity;
use Cbox\Id\Client\ValueObjects\Organization;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the caller to act for an organization — and optionally a minimum tier there.
 *
 *     Route::middleware(['auth', 'cbox-id.org'])->group(…);          // any member
 *     Route::middleware(['auth', 'cbox-id.org:admin'])->group(…);    // Admin or Owner
 *     Route::middleware(['cbox-id.token', 'cbox-id.org'])->group(…); // an API
 *
 * On success the {@see Organization} is on the request (`cbox_id_organization`) and in
 * the container, so a controller type-hints it and never re-reads a claim.
 *
 * Refusals: no principal at all is unauthenticated (401 JSON, or your login redirect).
 * A principal with no organization is a 403 `organization_required` for JSON; a browser
 * that signed in through Cbox ID is sent to the hosted organization picker instead, and
 * comes back to the page it asked for. Too low a tier is a 403
 * `insufficient_organization_role` — including when the instance did not say the tier,
 * because unknown is not enough.
 */
class RequireOrganization
{
    public const ATTRIBUTE = 'cbox_id_organization';

    public function __construct(private readonly CurrentPrincipal $principals) {}

    public function handle(Request $request, Closure $next, ?string $minimum = null): Response
    {
        $required = null;

        if ($minimum !== null && $minimum !== '') {
            $required = OrganizationRole::tryFrom($minimum)
                ?? throw ClientConfigurationException::because("cbox-id.org:{$minimum} names no organization role; use one of owner, admin, developer, member, viewer.");
        }

        $principal = $this->principals->resolve($request->user(), $request);

        if ($principal === null) {
            if ($request->expectsJson()) {
                return ErrorResponse::json('unauthenticated', 'Sign in first.', 401);
            }

            throw new AuthenticationException;
        }

        $organization = $principal->organization();

        if ($organization === null) {
            if ($request->expectsJson()) {
                return ErrorResponse::json('organization_required', OrganizationRequired::forPrincipal()->getMessage(), 403);
            }

            // Only a browser session can be sent to the picker: it has a callback to come
            // back to. A presented token cannot choose again by redirect.
            if ($principal instanceof Identity && config('cbox-id-client.organizations.picker', true) === true) {
                session()->put('url.intended', $request->fullUrl());

                return app(IdentityClient::class)->selectOrganization();
            }

            throw OrganizationRequired::forPrincipal();
        }

        if ($required !== null && ! $organization->roleAtLeast($required)) {
            return $request->expectsJson()
                ? ErrorResponse::json('insufficient_organization_role', "This needs the {$required->label()} role or higher in the organization.", 403, ['required' => $required->value])
                : abort(403, "This needs the {$required->label()} role or higher in the organization.");
        }

        $request->attributes->set(self::ATTRIBUTE, $organization);
        app()->instance(Organization::class, $organization);

        return ErrorResponse::from($next($request));
    }
}
