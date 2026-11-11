<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management;

/**
 * Cbox ID — the organization plane: the customer's own Cbox account, above every
 * environment it owns, with its projects, environments, members and keys.
 *
 * Cbox ID calls the account an organization; this SDK called it a workspace. This is the
 * client under its new name, from `CboxIdApi::organization()` or the container. It is a
 * {@see WorkspaceClient}, so code typed against the old name accepts it.
 */
class OrganizationClient extends WorkspaceClient {}
