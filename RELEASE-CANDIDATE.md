# laravel-id-client 0.16.0

Status: prepared local candidate. No public tag, registry publication or deployment has occurred.

Add app role CAS replacement, invitation receipts, definition activation, live service connections and the regenerated account/environment/workspace API. Preserve model-aware Laravel policy checks.

## Upgrade requirements

Workspace team.remove now requires a body with roles_version. Send role arrays and current versions for member writes; refresh on conflict. Environment access writes require environment_access_version. Custom Management implementations must implement invitation and replaceAppRoles. No ownership transfer through ordinary role assignment.

Dependency installation against public registries remains contingent on the approved release cascade. Local artifact checks prove the enclosed contents only; they do not claim public indexing.
