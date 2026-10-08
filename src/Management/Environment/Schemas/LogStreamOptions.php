<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * Datadog, S3 and GCS: the destination's settings, as stored. Never a credential — the API key, secret access key or service-account key is the encrypted secret. null for an HTTP collector.
 *
 * `LogStreamOptions` on the environment plane.
 */
readonly class LogStreamOptions implements JsonSerializable
{
    public function __construct(
        /** One of `datadoghq.com`, `us3.datadoghq.com`, `us5.datadoghq.com`, `datadoghq.eu`, `ap1.datadoghq.com`, `ap2.datadoghq.com`, `ddog-gov.com`. */
        public ?string $site = null,
        public ?string $service = null,
        public ?string $source = null,
        public ?string $hostname = null,
        /** Comma-separated `key:value` tags (`ddtags`). */
        public ?string $tags = null,
        public ?string $bucket = null,
        public ?string $region = null,
        public ?string $prefix = null,
        public ?string $accessKeyId = null,
        public ?string $roleArn = null,
        public ?string $externalId = null,
        /** One of `AES256`, `aws:kms`. */
        public ?string $sse = null,
        public ?string $kmsKeyId = null,
        public ?bool $pathStyle = null,
        public ?bool $gzip = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            site: Field::optional($data, 'site', 'LogStreamOptions', Value::string(...)),
            service: Field::optional($data, 'service', 'LogStreamOptions', Value::string(...)),
            source: Field::optional($data, 'source', 'LogStreamOptions', Value::string(...)),
            hostname: Field::optional($data, 'hostname', 'LogStreamOptions', Value::string(...)),
            tags: Field::optional($data, 'tags', 'LogStreamOptions', Value::string(...)),
            bucket: Field::optional($data, 'bucket', 'LogStreamOptions', Value::string(...)),
            region: Field::optional($data, 'region', 'LogStreamOptions', Value::string(...)),
            prefix: Field::optional($data, 'prefix', 'LogStreamOptions', Value::string(...)),
            accessKeyId: Field::optional($data, 'access_key_id', 'LogStreamOptions', Value::string(...)),
            roleArn: Field::optional($data, 'role_arn', 'LogStreamOptions', Value::string(...)),
            externalId: Field::optional($data, 'external_id', 'LogStreamOptions', Value::string(...)),
            sse: Field::optional($data, 'sse', 'LogStreamOptions', Value::string(...)),
            kmsKeyId: Field::optional($data, 'kms_key_id', 'LogStreamOptions', Value::string(...)),
            pathStyle: Field::optional($data, 'path_style', 'LogStreamOptions', Value::bool(...)),
            gzip: Field::optional($data, 'gzip', 'LogStreamOptions', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'service' => $this->service,
            'source' => $this->source,
            'hostname' => $this->hostname,
            'tags' => $this->tags,
            'bucket' => $this->bucket,
            'region' => $this->region,
            'prefix' => $this->prefix,
            'access_key_id' => $this->accessKeyId,
            'role_arn' => $this->roleArn,
            'external_id' => $this->externalId,
            'sse' => $this->sse,
            'kms_key_id' => $this->kmsKeyId,
            'path_style' => $this->pathStyle,
            'gzip' => $this->gzip,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
