<?php

/**
 * Twinfield browser login for tests, examples and dev scripts.
 *
 * Opens the Twinfield login in your browser, catches the redirect on
 * localhost and stores the refresh token in ~/.config/twinfield/token.json.
 *
 * Usage:
 *   composer twinfield:login             # catch the redirect on localhost
 *   composer twinfield:login -- --manual # paste the redirected URL yourself
 *   composer twinfield:login -- --scopes="openid twf.organisationUser offline_access"
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Setup\LoopbackLogin;
use SolveBeam\Twinfield\Tests\Support\Setup\Prompt;
use SolveBeam\Twinfield\Tests\Support\Setup\SetupException;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$manual = in_array('--manual', $arguments, true);
$scopes = LoopbackLogin::SCOPES;

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--scopes=')) {
        $scopes = array_values(array_filter(explode(' ', substr($argument, strlen('--scopes=')))));
    }
}

$config = Config::load();

if (!$config->hasCredentials()) {
    fwrite(STDERR, 'No Twinfield credentials yet. Run: composer twinfield:setup' . PHP_EOL);
    exit(1);
}

try {
    (new LoopbackLogin($config, new Prompt(STDIN, STDOUT, stream_isatty(STDIN))))->run($manual, $scopes);
} catch (SetupException $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Next: composer test:live', PHP_EOL;
