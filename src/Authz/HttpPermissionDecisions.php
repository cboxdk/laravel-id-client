<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Authz;

use Cbox\Id\Client\Contracts\PermissionDecisions;
use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Exceptions\AuthenticationFailed;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Throwable;

class HttpPermissionDecisions implements PermissionDecisions
{
    public function __construct(private readonly string $issuer, private readonly string $clientId, private readonly int $timeout = 10) {}

    public function check(#[SensitiveParameter] string $accessToken, string $subject, ?string $organization, array $permissions): PermissionDecision
    {
        $scheme = parse_url($this->issuer, PHP_URL_SCHEME);
        $host = parse_url($this->issuer, PHP_URL_HOST);
        if (! filter_var($this->issuer, FILTER_VALIDATE_URL) || ! ($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)))
            || parse_url($this->issuer, PHP_URL_USER) !== null || parse_url($this->issuer, PHP_URL_QUERY) !== null || parse_url($this->issuer, PHP_URL_FRAGMENT) !== null
            || $this->clientId === '' || $subject === '' || $organization === '' || $accessToken === '' || strlen($accessToken) > 32768 || preg_match('/[\x00-\x20\x7f]/', $accessToken)
            || $permissions === [] || count($permissions) > 50) {
            throw AuthenticationFailed::because('Permission decision request is not configured or valid.');
        }
        foreach ($permissions as $permission) {
            if (trim($permission) === '' || strlen($permission) > 255) {
                throw AuthenticationFailed::because('Permission decision request is not valid.');
            }
        }
        $permissions = array_values(array_unique($permissions));
        $response = null;
        try {
            $response = Http::acceptJson()->withToken($accessToken)->withoutRedirecting()->timeout(max(1, $this->timeout))
                ->withOptions(['stream' => true, 'read_timeout' => max(1, $this->timeout)])
                ->post(rtrim($this->issuer, '/').'/oauth/decisions', ['permission' => $permissions, 'subject' => $subject, 'org' => $organization]);
            if ($response->status() !== 200) {
                $error = AuthenticationFailed::because('Permission decision could not be verified.');
                $error->status = $response->status();
                throw $error;
            }
            $body = $response->toPsrResponse()->getBody();
            $raw = '';
            while (! $body->eof() && strlen($raw) <= 65536) {
                $chunk = $body->read(65537 - strlen($raw));
                if ($chunk === '') {
                    break;
                }
                $raw .= $chunk;
            }
            if (strlen($raw) > 65536) {
                throw AuthenticationFailed::because('Permission decision response exceeded its limit.');
            }
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw AuthenticationFailed::because('Permission decision response is invalid.');
            }

            return $this->parse($data, $subject, $organization, $permissions);
        } catch (AuthenticationFailed $error) {
            throw $error;
        } catch (Throwable) {
            // Transport exceptions may contain credentials or response bodies.
            throw AuthenticationFailed::because('Permission decision could not be verified.');
        } finally {
            $response?->toPsrResponse()->getBody()->close();
        }
    }

    /** @param array<array-key, mixed> $data
     * @param  list<string>  $permissions
     */
    private function parse(array $data, string $subject, ?string $organization, array $permissions): PermissionDecision
    {
        $who = $data['subject'] ?? null;
        $results = $data['results'] ?? null;
        $active = $data['organization_active'] ?? null;
        $allowed = $data['allowed'] ?? null;
        $role = $data['org_role'] ?? null;
        if (($data['mode'] ?? null) !== 'rbac' || ! is_array($who) || ($who['type'] ?? null) !== 'user' || ($who['id'] ?? null) !== $subject
            || ! array_key_exists('organization', $data) || $data['organization'] !== $organization || ($data['client_id'] ?? null) !== $this->clientId
            || ! is_bool($active) || ! is_bool($allowed) || ! array_key_exists('org_role', $data)
            || ($role !== null && (! is_string($role) || OrganizationRole::tryFrom($role) === null))
            || ! is_array($results) || ! array_is_list($results) || count($results) !== count($permissions)) {
            throw AuthenticationFailed::because('Permission decision response binding is invalid.');
        }
        $checks = [];
        foreach ($results as $result) {
            if (! is_array($result) || ! is_string($result['permission'] ?? null) || ! is_bool($result['allowed'] ?? null)
                || ! in_array($result['permission'], $permissions, true) || array_key_exists($result['permission'], $checks)) {
                throw AuthenticationFailed::because('Permission decision result is invalid.');
            }
            $checks[$result['permission']] = $result['allowed'];
        }
        $decision = new PermissionDecision($subject, $organization, $this->clientId, is_string($role) ? OrganizationRole::from($role) : null, $active, $checks);
        if ($allowed !== $decision->allowed()) {
            throw AuthenticationFailed::because('Permission decision result is inconsistent.');
        }

        return $decision;
    }
}
