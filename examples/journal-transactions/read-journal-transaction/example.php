<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a journal day book and transaction number, e.g. MEMO 202600003.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->journalTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo $exception->deleted ? 'Deleted' : 'Not found: ' . $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', period ', $header->period, ', regime ',
    $transaction->regime->value, PHP_EOL;
echo 'Total:       ', $transaction->debit(), ' debit = ', $transaction->credit(), ' credit ', $header->currency,
    PHP_EOL;

foreach ($transaction->details as $line) {
    echo sprintf(
        '  %-6s %-6s %-6s %10s  %-7s %8s  %s',
        $line->debitCredit->value,
        $line->account->code,
        $line->dim2->code ?? '',
        $line->amounts->value,
        $line->vatCode ?? '',
        $line->vatValue ?? '',
        $line->description ?? '',
    ), PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo sprintf(
        '  %-6s %-6s %-6s %10s  VAT %s on %s',
        $line->debitCredit->value,
        $line->account->code,
        '',
        $line->amounts->value,
        $line->vatCode,
        $line->vatTurnover ?? '-',
    ), PHP_EOL;
}
