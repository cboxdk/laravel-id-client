<?php

declare(strict_types=1);
use Cbox\Id\Client\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// Shared before any suite runs, so a file exercising tokens does not depend on
// another file having been loaded first.
require_once __DIR__.'/Helpers.php';
