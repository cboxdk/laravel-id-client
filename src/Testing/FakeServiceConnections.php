<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Contracts\ServiceConnections;
use Cbox\Id\Client\ValueObjects\ServiceConnection;
use Cbox\Id\Client\ValueObjects\ServiceConnectionLease;
use LogicException;

/** Explicit queued results; an unconfigured fake never invents a successful grant. */
class FakeServiceConnections implements ServiceConnections
{
    /** @var list<string> */
    public array $calls = [];

    /** @param list<ServiceConnection|ServiceConnectionLease|bool|\Throwable> $results */
    public function __construct(private array $results = []) {}

    public function connect(string $accessToken, string $organization, string $service, string $audience): ServiceConnection
    {
        $result = $this->next('connect');
        if (! $result instanceof ServiceConnection || $result->organizationId !== $organization || $result->service !== $service || $result->audience !== $audience) {
            throw new LogicException('Queue a matching service connection.');
        }

        return $result;
    }

    public function status(ServiceConnection $connection): bool
    {
        $result = $this->next('status');
        if (! is_bool($result)) {
            throw new LogicException('Queue an explicit connection status.');
        }

        return $result;
    }

    public function lease(ServiceConnection $connection): ServiceConnectionLease
    {
        $result = $this->next('lease');
        if (! $result instanceof ServiceConnectionLease || $result->connection != $connection) {
            throw new LogicException('Queue a lease for this connection.');
        }

        return $result;
    }

    public function revoke(string $accessToken, ServiceConnection $connection): void
    {
        if ($this->next('revoke') !== true) {
            throw new LogicException('Queue explicit revocation confirmation.');
        }
    }

    private function next(string $method): ServiceConnection|ServiceConnectionLease|bool
    {
        $this->calls[] = $method;
        if ($this->results === []) {
            throw new LogicException('No service connection result was queued.');
        }
        $result = array_shift($this->results);
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
