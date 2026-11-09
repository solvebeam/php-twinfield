<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionInput;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
$journal = $twinfield->journalTransactions();

// In your own application: your opening balance day book and balance sheet accounts, e.g. the
// bank account and equity.
[$bank, $equity] = Example::generalLedgerAccounts($twinfield, 'BAS', 2);
$year = (int) date('Y');

// An opening balance goes in period 0 of the year. Twinfield refuses it in another period.
$input = JournalTransactionInput::create('BEGINBALANS', new DateTimeImmutable($year . '-01-01'))
    ->period(FinancialPeriod::of($year, 0))
    ->debit($bank, '2500.00', description: 'Opening balance')
    ->credit($equity, '2500.00', description: 'Opening balance');

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

echo 'Created: ', $transaction->dayBook(), ' ', $transaction->number(), ', period ', $transaction->header->period,
    PHP_EOL;

// Clean up: delete the provisional example transaction again.
$journal->delete($transaction->dayBook(), $transaction->number(), 'php-twinfield example');

echo 'Deleted: ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
