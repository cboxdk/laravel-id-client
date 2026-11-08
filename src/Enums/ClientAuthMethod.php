<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Enums;

/**
 * How a client proves who it is at the token, device and backchannel endpoints
 * (RFC 6749 §2.3, RFC 7523). The values are the registered
 * `token_endpoint_auth_method` names.
 */
enum ClientAuthMethod: string
{
    /** A public client: `client_id` only. Cbox ID allows it for CIBA only on resources that permit public backchannel requests. */
    case None = 'none';

    /** HTTP Basic with the client id and secret. */
    case ClientSecretBasic = 'client_secret_basic';

    /** `client_id` and `client_secret` in the form body. */
    case ClientSecretPost = 'client_secret_post';

    /** A short-lived JWT signed with the client's private key (RFC 7523). */
    case PrivateKeyJwt = 'private_key_jwt';
}
