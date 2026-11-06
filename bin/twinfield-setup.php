<?php

/**
 * Guided Twinfield setup: client ID, client secret, redirect URI, browser
 * login and default company. Run it again to change any of these.
 *
 * Usage:
 *   composer twinfield:setup
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Setup\Prompt;
use SolveBeam\Twinfield\Tests\Support\Setup\Setup;
use SolveBeam\Twinfield\Tests\Support\Setup\SetupException;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $config = (new Setup(new Prompt(STDIN, STDOUT, stream_isatty(STDIN))))->ensure(Config::load(), all: true);
} catch (SetupException $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Setup complete. Try an example: php examples/offices/list-offices/example.php', PHP_EOL;
