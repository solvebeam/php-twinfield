<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$journal = $twinfield->journalTransactions();

// In your own application: your journal day book and accounts, e.g. 4500 and 4510.
[$from, $to] = Example::generalLedgerAccounts($twinfield, 'PNL', 2);
$date = new DateTimeImmutable('today');

// A correction: move costs that were booked on the wrong account. Every line has its own side
// and the lines must balance; the input checks that before anything is sent.
$input = JournalTransactionInput::create('MEMO', $date)
    ->freeText(1, 'Correction')
    ->debit($to, '125.00', description: 'Booked on the wrong account')
    ->credit($from, '125.00', description: 'Booked on the wrong account');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to create it as provisional transaction and delete it again:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml((string) $twinfield->companyCode())), PHP_EOL;

    exit(0);
}

try {
    $transaction = $journal->create($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ' (', $transaction->header->status->name, ')',
    ', period ', $transaction->header->period, PHP_EOL;

foreach ($transaction->details as $line) {
    echo '  ', $line->id, ' ', $line->dim1->code, ' ', $line->debitCredit->value, ' ', $line->value,
        PHP_EOL;
}

// Clean up: delete the provisional example transaction again.
$journal->delete($transaction->dayBook(), $transaction->number(), 'twinfield-php example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
