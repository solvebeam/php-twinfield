<?php

/**
 * Turns recorded responses (tests/Fixtures/raw, gitignored) into anonymized
 * fixtures (committed): XML in tests/Fixtures/soap, JSON of the REST API in tests/Fixtures/rest.
 *
 * Usage:
 *   composer twinfield:anonymize                # all recordings
 *   composer twinfield:anonymize -- search-dim  # only recordings starting with this name
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Fixtures\JsonAnonymizer;
use SolveBeam\Twinfield\Tests\Support\Fixtures\XmlAnonymizer;

require dirname(__DIR__) . '/vendor/autoload.php';

$rawDir = dirname(__DIR__) . '/tests/Fixtures/raw';
$fixturesDir = dirname(__DIR__) . '/tests/Fixtures';
$filter = $argv[1] ?? '';

$files = array_merge(
    glob($rawDir . '/' . $filter . '*.xml') ?: [],
    glob($rawDir . '/' . $filter . '*.json') ?: [],
);

echo sprintf('Found %d recording(s) in %s', count($files), $rawDir), PHP_EOL;

if ($files === []) {
    echo 'Record first: TWINFIELD_RECORD=1 composer test:live', PHP_EOL;
    exit(0);
}

$anonymizer = XmlAnonymizer::fromConfigDir(Config::load()->configDir);
$jsonAnonymizer = new JsonAnonymizer($anonymizer);
$written = 0;
$failed = 0;

foreach ($files as $index => $file) {
    $name = basename($file);
    $before = $anonymizer->replacedCount();

    $isJson = str_ends_with($name, '.json');

    try {
        $contents = (string) file_get_contents($file);
        $anonymized = $isJson ? $jsonAnonymizer->anonymize($contents) : $anonymizer->anonymize($contents);
    } catch (RuntimeException $exception) {
        echo sprintf('[%d/%d] %s: failed, %s', $index + 1, count($files), $name, $exception->getMessage()), PHP_EOL;
        $failed++;

        continue;
    }

    file_put_contents($fixturesDir . ($isJson ? '/rest/' : '/soap/') . $name, $anonymized);
    $written++;

    echo sprintf(
        '[%d/%d] %s: written, %d value(s) replaced',
        $index + 1,
        count($files),
        $name,
        $anonymizer->replacedCount() - $before,
    ), PHP_EOL;
}

echo PHP_EOL, 'Values kept as is (check that none of these is real business data):', PHP_EOL;

foreach ($anonymizer->keptValues() as $value => $count) {
    echo sprintf('  %4dx  %s', $count, mb_strimwidth((string) $value, 0, 80, '…')), PHP_EOL;
}

echo PHP_EOL, sprintf('Done: %d written, %d failed. Review with: git diff tests/Fixtures', $written, $failed), PHP_EOL;

exit($failed > 0 ? 1 : 0);
