<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use Cbox\Id\Client\Contracts\UserProperties;
use Cbox\Id\Client\Exceptions\UserPropertiesFailed;
use Cbox\Id\Client\IdentityClient;
use Cbox\Id\Client\Support\Background;
use Cbox\Id\Client\Support\PublicId;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Carbon;
use JsonException;
use stdClass;
use Throwable;

/**
 * A person's shared properties (`preferences`, or another namespace), read from the copy
 * this app keeps, so a sign-in or a page does not wait for Cbox ID.
 *
 *     $preferences = app(KeptUserProperties::class)->read($user->id, 'preferences');
 *
 * - With a kept copy, {@see read()} returns it, however old it is within `keep_for`
 *   (thirty days). One older than `fresh_for` (five minutes) is read again in the
 *   background: a queued {@see RefreshKeptUserProperties}, or after the response in an
 *   app with no queue worker.
 * - With nothing kept (the person's first sign-in in this app), it asks Cbox ID now.
 * - Cbox ID's `user.preferences.updated` / `user.metadata.updated` webhook refreshes the
 *   kept copy on the webhook worker, so a change made elsewhere arrives without anybody
 *   reading. The package registers that itself.
 * - A copy is only ever replaced by a newer version. A 401, 403 or 404 from Cbox ID drops
 *   it (access was taken away); an outage leaves it.
 *
 * The background read uses the app's own machine token (`user_properties:read`), so the
 * OAuth client needs a read grant on the namespace. A write still goes through
 * {@see UserProperties::patch()} with the version you read; hand what it returns to
 * {@see remember()}.
 *
 * Kept per issuer, client, person and namespace, as JSON text: objects and lists come
 * back exactly as Cbox ID sent them.
 */
class KeptUserProperties
{
    /** The scope the machine token needs to read properties. */
    public const string SCOPE = 'user_properties:read';

    public const int DEFAULT_FRESH_FOR = 300;

    public const int DEFAULT_KEEP_FOR = 2_592_000;

    private const string PREFIX = 'cbox-id-client.user-properties.';

    private readonly int $freshFor;

    private readonly int $keepFor;

    public function __construct(
        private readonly UserProperties $properties,
        private readonly IdentityClient $identity,
        private readonly Repository $cache,
        private readonly string $issuer,
        private readonly string $clientId,
        int $freshFor = self::DEFAULT_FRESH_FOR,
        int $keepFor = self::DEFAULT_KEEP_FOR,
    ) {
        $this->freshFor = max(0, $freshFor);
        $this->keepFor = max(1, $this->freshFor, $keepFor);
    }

    /**
     * The person's document: the kept copy, or Cbox ID's answer when nothing is kept.
     *
     * @param  PropertyToken|null  $token  used for the read when nothing is kept, e.g. the
     *                                     access token of the sign-in that just completed;
     *                                     without one the app's machine token is used
     *
     * @throws UserPropertiesFailed when nothing is kept and Cbox ID cannot answer
     */
    public function read(string $user, string $namespace, ?PropertyToken $token = null): PropertyDocument
    {
        $kept = $this->kept($user, $namespace);

        if ($kept === null) {
            return $this->store($this->properties->read($user, $namespace, $token ?? $this->machineToken()));
        }

        if (Carbon::now()->getTimestamp() - $kept['at'] > $this->freshFor) {
            Background::dispatch(
                'cbox-id-client:user-properties:'.$this->key($user, $namespace),
                static fn (): PendingDispatch => RefreshKeptUserProperties::dispatch($user, $namespace),
                function () use ($user, $namespace): void {
                    (new RefreshKeptUserProperties($user, $namespace))->handle($this);
                },
            );
        }

        return $kept['document'];
    }

    /**
     * Ask Cbox ID now, with the app's machine token, and keep the answer.
     *
     * @throws UserPropertiesFailed a 401, 403 or 404 also drops the kept copy
     */
    public function refresh(string $user, string $namespace): PropertyDocument
    {
        try {
            return $this->store($this->readWithMachineToken($user, $namespace));
        } catch (UserPropertiesFailed $error) {
            if (in_array($error->status, [401, 403, 404], true)) {
                $this->forget($user, $namespace);
            }

            throw $error;
        }
    }

    /**
     * Cbox ID said this document is now at `$version`. A kept copy that is older is read
     * again, here: this runs on the webhook worker. A document this app does not keep is
     * left alone. Never throws: when the read fails the copy is marked stale instead, so
     * the next {@see read()} refreshes it.
     */
    public function changed(string $user, string $namespace, int $version): void
    {
        try {
            $kept = $this->kept($user, $namespace);

            if ($kept === null || $kept['document']->version >= $version) {
                return;
            }

            try {
                $this->refresh($kept['document']->userId, $namespace);
            } catch (UserPropertiesFailed $error) {
                if (! in_array($error->status, [401, 403, 404], true)) {
                    $this->write($kept['document'], 0);
                }
            }
        } catch (Throwable $error) {
            report($error);
        }
    }

    /** Keep a document you already hold, such as the answer to a patch. Newer versions only. */
    public function remember(PropertyDocument $document): PropertyDocument
    {
        return $this->store($document);
    }

    public function forget(string $user, string $namespace): void
    {
        try {
            $this->cache->forget($this->key($user, $namespace));
        } catch (Throwable $error) {
            report($error);
        }
    }

    private function readWithMachineToken(string $user, string $namespace): PropertyDocument
    {
        try {
            return $this->properties->read($user, $namespace, $this->machineToken());
        } catch (UserPropertiesFailed $error) {
            if ($error->status !== 401) {
                throw $error;
            }

            // The kept machine token was refused (revoked, or the secret rotated): one new one.
            $this->identity->forgetMachineToken([self::SCOPE]);

            return $this->properties->read($user, $namespace, $this->machineToken());
        }
    }

    private function machineToken(): PropertyToken
    {
        return new PropertyToken($this->identity->machineToken([self::SCOPE]));
    }

    /** Keep the document unless a newer one is already kept; returns the one now kept. */
    private function store(PropertyDocument $document): PropertyDocument
    {
        $kept = $this->kept($document->userId, $document->namespace);

        if ($kept !== null && $kept['document']->version > $document->version) {
            return $kept['document'];
        }

        $this->write($document, Carbon::now()->getTimestamp());

        return $document;
    }

    private function write(PropertyDocument $document, int $at): void
    {
        try {
            $this->cache->put($this->key($document->userId, $document->namespace), [
                'at' => $at,
                'user' => $document->userId,
                'namespace' => $document->namespace,
                'version' => $document->version,
                'data' => json_encode($document->data, JSON_THROW_ON_ERROR),
            ], $this->keepFor);
        } catch (Throwable $error) {
            // The document is still good for this request; the next one asks again.
            report($error);
        }
    }

    /** @return array{at: int, document: PropertyDocument}|null */
    private function kept(string $user, string $namespace): ?array
    {
        try {
            $stored = $this->cache->get($this->key($user, $namespace));
        } catch (Throwable $error) {
            report($error);

            return null;
        }

        if (! is_array($stored) || ! is_int($stored['at'] ?? null) || ! is_string($stored['user'] ?? null)
            || ($stored['namespace'] ?? null) !== $namespace || ! is_int($stored['version'] ?? null) || ! is_string($stored['data'] ?? null)) {
            return null;
        }

        try {
            $data = json_decode($stored['data'], false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return $data instanceof stdClass
            ? ['at' => $stored['at'], 'document' => new PropertyDocument($stored['user'], $namespace, $data, $stored['version'])]
            : null;
    }

    /** One entry per person whichever spelling of their id is used (`usr_01J…` or the raw one). */
    private function key(string $user, string $namespace): string
    {
        return self::PREFIX.hash('sha256', json_encode([$this->issuer, $this->clientId, PublicId::raw($user), $namespace], JSON_THROW_ON_ERROR));
    }
}
