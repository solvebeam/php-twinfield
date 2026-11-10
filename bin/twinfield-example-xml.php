<?php

/**
 * Writes the request and response XML into the README of the examples in
 * tests/Support/Docs/ExampleXmlDefinitions.php, with a link to the Twinfield documentation.
 *
 * Usage:
 *   composer docs:xml
 */

declare(strict_types=1);

use SolveBeam\Twinfield\Tests\Support\Docs\ExampleXml;
use SolveBeam\Twinfield\Tests\Support\Docs\ExampleXmlDefinitions;

require dirname(__DIR__) . '/vendor/autoload.php';

$examples = array_keys(ExampleXmlDefinitions::examples());
$total = count($examples);
$changed = 0;

echo sprintf('Updating the XML of %d examples', $total), PHP_EOL;

foreach ($examples as $index => $example) {
    $file = ExampleXml::examplesDir() . '/' . $example . '/README.md';
    $current = (string) file_get_contents($file);
    $updated = ExampleXml::readme($example, $current);

    if ($updated === $current) {
        echo sprintf('[%d/%d] %s: up to date', $index + 1, $total, $example), PHP_EOL;

        continue;
    }

    file_put_contents($file, $updated);
    $changed++;

    echo sprintf('[%d/%d] %s: updated', $index + 1, $total, $example), PHP_EOL;
}

echo sprintf('Done: %d of %d READMEs updated.', $changed, $total), PHP_EOL;
