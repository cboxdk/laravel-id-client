<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates the typed management clients from the OpenAPI documents Cbox ID publishes,
 * vendored in `openapi/`. See `bin/generate-management`.
 */
final class ManagementGenerator
{
    /** Directories (relative to the package root) whose every file is generated. */
    public const GENERATED_DIRECTORIES = [
        'src/Management/Environment',
        'src/Management/Workspace',
        'src/Management/Platform',
        'src/Management/Account',
    ];

    public function __construct(private readonly string $root) {}

    /**
     * Every generated file, keyed by its path relative to the package root.
     *
     * @return array<string, string>
     */
    public function generate(): array
    {
        $files = [];

        foreach (PlaneConfig::all() as $config) {
            $files = [...$files, ...$this->plane($config)->render()];
        }

        ksort($files);

        return $files;
    }

    public function plane(PlaneConfig $config): PlaneGenerator
    {
        $spec = Yaml::parseFile($this->root.'/openapi/'.$config->file.'.yaml');

        if (! is_array($spec)) {
            throw new RuntimeException("openapi/{$config->file}.yaml is not a YAML mapping");
        }

        /** @var array<string, mixed> $spec */
        return new PlaneGenerator($spec, $config);
    }

    /**
     * The generated files that exist on disk now, keyed like {@see self::generate()}.
     *
     * @return array<string, string>
     */
    public function current(): array
    {
        $files = [];

        foreach (PlaneConfig::all() as $config) {
            $client = "src/Management/{$config->className}.php";

            if (is_file($this->root.'/'.$client)) {
                $files[$client] = (string) file_get_contents($this->root.'/'.$client);
            }
        }

        foreach (self::GENERATED_DIRECTORIES as $directory) {
            if (! is_dir($this->root.'/'.$directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root.'/'.$directory, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $relative = substr($file->getPathname(), strlen($this->root) + 1);
                    $files[$relative] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Paths that differ between what is generated and what is on disk: changed, missing,
     * or left over from an operation the specs no longer have.
     *
     * @return list<string>
     */
    public function stale(): array
    {
        $want = $this->generate();
        $have = $this->current();
        $stale = [];

        foreach ($want as $path => $code) {
            if (($have[$path] ?? null) !== $code) {
                $stale[] = $path;
            }
        }

        foreach (array_keys($have) as $path) {
            if (! isset($want[$path])) {
                $stale[] = $path;
            }
        }

        sort($stale);

        return $stale;
    }

    /**
     * Write every generated file and delete the ones no longer generated.
     *
     * @return array<string, string>
     */
    public function write(): array
    {
        $want = $this->generate();

        foreach (array_keys($this->current()) as $path) {
            if (! isset($want[$path])) {
                unlink($this->root.'/'.$path);
            }
        }

        foreach ($want as $path => $code) {
            $absolute = $this->root.'/'.$path;

            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0777, true);
            }

            file_put_contents($absolute, $code);
        }

        return $want;
    }
}
