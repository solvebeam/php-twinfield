<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectMessage;
use SolveBeam\Twinfield\PayAndCollect\PayRunModification;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12 2001`: authorises run 12 without the invoices of supplier 2001, posts it, saves
// the payment file(s) and ends the run. Posting can't be undone.
[, $payType, $id, $exclude] = array_pad($argv ?? [], 4, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID> [<relation to exclude>]', PHP_EOL;

    exit(0);
}

$payAndCollect = $twinfield->payAndCollect();
$run = $payAndCollect->get($payType, (int) $id);
$version = $run->version;

$print = static function (string $step, PayAndCollectMessage ...$messages): void {
    echo $step, PHP_EOL;

    foreach ($messages as $message) {
        echo '  ', $message->type->name, ': ', $message->text, PHP_EOL;
    }
};

try {
    if ($exclude !== null) {
        $modification = PayRunModification::of($run);

        foreach ($run->summaries as $summary) {
            if ($summary->relation === $exclude) {
                $modification = $modification->excludeSummary($summary->id);
            }
        }

        $version = $payAndCollect->modify($modification, $version);
        $print('Modified: excluded ' . $exclude, ...$version->messages);
    }

    $version = $payAndCollect->authorise($run, $version);
    $print('Authorised', ...$version->messages);

    $version = $payAndCollect->post($run, $version);
    $print('Posted to the in-transfer ledger', ...$version->messages);

    $files = $payAndCollect->createFiles($run, $version);
    $print('Created ' . count($files->files) . ' file(s)', ...$files->version->messages);

    foreach ($files->files as $index => $contents) {
        $file = sprintf('%s/pay-run-%s-%d-%d.xml', sys_get_temp_dir(), $run->payType, $run->id, $index + 1);
        file_put_contents($file, $contents);

        echo '  Saved to ', $file, ' (', strlen($contents), ' bytes)', PHP_EOL;
    }

    $print('Finished', ...$payAndCollect->finish($run, $files->version));
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}
