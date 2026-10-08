<?php

declare(strict_types=1);

use Cbox\Id\Client\Generator\ManagementGenerator;
use Cbox\Id\Client\Management\AccountClient;
use Cbox\Id\Client\Management\Environment\Operations as EnvironmentOperations;
use Cbox\Id\Client\Management\EnvironmentClient;
use Cbox\Id\Client\Management\ManagementClient;
use Cbox\Id\Client\Management\PlatformClient;
use Cbox\Id\Client\Management\Transport\Danger;
use Cbox\Id\Client\Management\WorkspaceClient;

/*
 * The generated clients against what generates them.
 *
 * 1. Drift: regenerating from openapi/*.yaml must reproduce src/Management/ exactly — a
 *    hand edit to generated code, or a spec refreshed without regenerating, fails here.
 * 2. Surface: every method of every client, as a caller sees it, against a committed
 *    snapshot — so a spec change that renames, drops or re-types a method shows up in the
 *    diff of a pull request, not in somebody's production logs.
 *
 *    Update the snapshot after an intended change: UPDATE_SNAPSHOTS=1 vendor/bin/pest --filter=surface
 */

it('is exactly what the vendored specs generate', function (): void {
    $stale = (new ManagementGenerator(dirname(__DIR__)))->stale();

    expect($stale)->toBe([], 'Generated management code is stale. Run `composer generate` and commit the result.');
});

function managementSurface(): string
{
    $lines = [];
    $clients = [
        'env' => new EnvironmentClient(baseUrl: 'https://acme.test', apiKey: 'cbid_env_x'),
        'workspace' => new WorkspaceClient(apiKey: 'cbid_ws_x'),
        'platform' => new PlatformClient(accessToken: 't'),
        'me' => new AccountClient(baseUrl: 'https://acme.test', accessToken: 't'),
    ];

    $walk = function (object $resource, string $prefix) use (&$walk, &$lines): void {
        $class = new ReflectionClass($resource);

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic() || $method->getDeclaringClass()->getName() === ManagementClient::class) {
                continue;
            }

            $params = array_map(static function (ReflectionParameter $p): string {
                return trim(($p->getType() ?? '').' $'.$p->getName().($p->isOptional() ? ' = '.var_export($p->getDefaultValue(), true) : ''));
            }, $method->getParameters());

            preg_match('/@return (.+)$/m', (string) $method->getDocComment(), $return);
            $lines[] = "{$prefix}->{$method->getName()}(".str_replace(["array (\n)", 'NULL'], ['[]', 'null'], implode(', ', $params)).'): '.trim($return[1] ?? (string) $method->getReturnType());
        }

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($resource);

            if (is_object($value) && str_contains($value::class, '\\Resources\\')) {
                $walk($value, "{$prefix}->{$property->getName()}");
            }
        }
    };

    foreach ($clients as $name => $client) {
        $walk($client, '$'.$name);
    }

    return implode("\n", $lines)."\n";
}

it('keeps the generated surface a caller sees', function (): void {
    $path = __DIR__.'/Fixtures/management-surface.txt';
    $surface = managementSurface();

    if (getenv('UPDATE_SNAPSHOTS') === '1' || ! is_file($path)) {
        file_put_contents($path, $surface);
    }

    expect($surface)->toBe(file_get_contents($path));
});

it('names methods after the server\'s actions, path parameters first', function (): void {
    $surface = managementSurface();

    expect($surface)
        ->toContain('$env->apps->secrets->rotate(string $id, array $body, ?Cbox\Id\Client\Management\Transport\CallOptions $options = null)')
        ->toContain('$workspace->environments->create(array $body')
        ->toContain('$env->sso->connections->requireSso(')
        ->toContain('$env->organizations->listAll(array $query = [], ?Cbox\Id\Client\Management\Transport\CallOptions $options = null): Generator<int, Organization, mixed, void>')
        ->toContain('$me->sessions->revokeOthers(');
});

it('describes every operation\'s scope and danger in its table', function (): void {
    $rotate = EnvironmentOperations::spec('apps.secrets.rotate');

    expect($rotate->method)->toBe('POST')
        ->and($rotate->path)->toBe('/apps/{id}/secrets')
        ->and($rotate->scope)->toBe('apps:write')
        ->and($rotate->danger)->toBe(Danger::Critical)
        ->and($rotate->approval)->toBeTrue()
        ->and(EnvironmentOperations::all())->toHaveCount(count(EnvironmentOperations::keys()))
        ->and(fn () => EnvironmentOperations::spec('nope'))->toThrow(InvalidArgumentException::class);
});
