<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Generator;

/** One namespace of the action tree — `apps`, `apps.secrets` — and so one resource class. */
final class Node
{
    /** @var array<string, Node> */
    public array $children = [];

    /** @var list<Operation> */
    public array $ops = [];

    /** @param list<string> $path */
    public function __construct(public readonly array $path = []) {}
}
