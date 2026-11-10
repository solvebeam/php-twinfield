<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `SEPANLCT 12`: the pay type and the ID of the run, see the list-pay-runs example.
[, $payType, $id] = array_pad($argv ?? [], 3, null);

if ($payType === null || $id === null) {
    echo 'Usage: php example.php <pay type> <run ID>', PHP_EOL;

    exit(0);
}

$run = $twinfield->payAndCollect()->get($payType, (int) $id);

echo $run->payType, ' ', $run->id, ': ', $run->runType->name, ', ', $run->phase->name, ' ', $run->status->name,
    ', touched ', $run->version->touched, PHP_EOL;

foreach ($run->summaries as $summary) {
    echo PHP_EOL, 'Summary ', $summary->id, ': ', $summary->relation, ' ', $summary->relationName, ', ',
        $summary->currency, ' ', $summary->value(), $summary->included ? '' : ' (excluded)',
        ', IBAN ', $summary->relationBank->iban ?? 'none', PHP_EOL;

    foreach ($summary->details as $detail) {
        echo '  ', $detail->id, ': invoice ', $detail->invoiceNumber, ' (', $detail->transaction, ' ',
            $detail->transactionNumber, '), due ', $detail->invoiceDueDate->format('Y-m-d'), ', ', $detail->value,
            ' of ', $detail->originalValue, $detail->included ? '' : ' (excluded)', PHP_EOL;
    }
}
