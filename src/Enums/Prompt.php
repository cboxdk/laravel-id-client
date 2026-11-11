<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Enums;

/**
 * The `prompt` values Cbox ID understands on its authorize endpoint.
 *
 * The first four are OpenID Connect Core §3.1.2.1. `create` is "Initiating User
 * Registration via OpenID Connect 1.0": the hosted page opens on sign-up. The last two
 * are Cbox ID's own and drive its hosted organization step: the picker, and "create a
 * team" (the person becomes its Owner and the authorization continues bound to the new
 * organization).
 */
enum Prompt: string
{
    /** Silent: fail with `login_required` rather than show anything. */
    case None = 'none';

    /** Force a fresh sign-in, even with a live session — "add account". */
    case Login = 'login';

    case Consent = 'consent';

    case SelectAccount = 'select_account';

    /** Open the hosted page on "create an account" instead of sign-in. */
    case Create = 'create';

    /** Always show the hosted organization picker. */
    case SelectOrganization = 'select_organization';

    /** Show the hosted "create an organization" step. */
    case CreateOrganization = 'create_organization';
}
