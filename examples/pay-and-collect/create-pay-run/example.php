<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectException;
use SolveBeam\Twinfield\PayAndCollect\PayRunInput;
use SolveBeam\Twinfield\PayAndCollect\PayRunMethod;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `SEPANLCT 123`: a payment run for purchase invoice 123. Without an invoice number, every
// open invoice that is due within 14 days is selected.
[, $payType, $invoiceNumber] = array_pad($argv ?? [], 3, null);

if ($payType === null) {
    echo 'Usage: php example.php <pay type> [<invoice number>]', PHP_EOL;

    exit(0);
}

$payDate = new DateTimeImmutable('tomorrow');
$input = PayRunInput::create($payType, $payDate, $payDate->format('Y/m'))
    ->method(PayRunMethod::Summarise)
    ->maxDueDate($payDate->modify('+14 days'));

if ($invoiceNumber !== null) {
    $input = $input->invoiceNumber($invoiceNumber);
}

try {
    $created = $twinfield->payAndCollect()->create($input);
} catch (PayAndCollectException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

foreach ($created->messages as $message) {
    echo $message->type->name, ': ', $message->text, PHP_EOL;
}

$run = $created->run;

echo 'Created ', $run->payType, ' ', $run->id, ' with ', count($run->summaries), ' summaries', PHP_EOL;

foreach ($run->summaries as $summary) {
    echo '  ', $summary->relation, ' ', $summary->relationName, ': ', $summary->currency, ' ', $summary->value(),
        PHP_EOL;
}
