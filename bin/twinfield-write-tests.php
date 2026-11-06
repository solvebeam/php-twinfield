<?php

/**
 * Runs the write tests (tests/Write) after you confirm the company code.
 *
 * Usage:
 *   composer test:write                      # uses TWINFIELD_COMPANY
 *   composer test:write -- --company=1002_3
 *   composer test:write -- --company=1002_3 --filter=SomeTest   # extra args go to PHPUnit
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Tests\Support\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::load();
$company = $config->company();
$phpunitArguments = [];

foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
    if (str_starts_with($argument, '--company=')) {
        $company = substr($argument, strlen('--company='));
    } else {
        $phpunitArguments[] = $argument;
    }
}

$allowed = $config->writeCompanies();

if (!in_array($company, $allowed, true)) {
    fwrite(STDERR, sprintf(
        'Refused: company "%s" is not in TWINFIELD_WRITE_COMPANIES (%s).' . PHP_EOL,
        $company,
        implode(', ', $allowed) ?: 'empty',
    ));
    exit(1);
}

if (!stream_isatty(STDIN)) {
    fwrite(STDERR, 'Refused: write tests need an interactive terminal for confirmation.' . PHP_EOL);
    exit(1);
}

echo PHP_EOL;
echo 'Write tests will CREATE AND CHANGE DATA in the Twinfield production organisation.', PHP_EOL;
echo sprintf('Administration: %s', $company), PHP_EOL;
echo PHP_EOL;
echo sprintf('Type "%s" to confirm: ', $company);

if (trim((string) fgets(STDIN)) !== $company) {
    fwrite(STDERR, 'Not confirmed, nothing was run.' . PHP_EOL);
    exit(1);
}

$command = array_merge(
    [PHP_BINARY, dirname(__DIR__) . '/vendor/bin/phpunit', '--testsuite=write'],
    $phpunitArguments,
);

$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, null, array_merge(getenv(), [
    'TWINFIELD_COMPANY' => $company,
    'TWINFIELD_ALLOW_WRITES' => $company,
]));

exit(is_resource($process) ? proc_close($process) : 1);
