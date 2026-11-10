<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

if (!isset($argv[1], $argv[2])) {
    echo 'Pass a purchase day book and transaction number, e.g. INK 202600021.', PHP_EOL;

    exit(0);
}

try {
    $transaction = $twinfield->purchaseTransactions()->get($argv[1], (int) $argv[2]);
} catch (TransactionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$header = $transaction->header;
$total = $transaction->total;

echo 'Transaction: ', $header->dayBook, ' ', $header->number, ' (', $header->status->name, ')', PHP_EOL;
echo 'Invoice:     ', $transaction->invoiceNumber, $transaction->isCreditNote() ? ' (credit note)' : '', PHP_EOL;
echo 'Date:        ', $header->date->format('Y-m-d'), ', due ', $transaction->dueDate?->format('Y-m-d') ?? '-',
    ', period ', $header->period, PHP_EOL;
echo 'Supplier:    ', $transaction->supplier()->code, ' ', $transaction->supplier()->name, ' on ',
    $total->dim1->code, PHP_EOL;
echo 'Total:       ', $transaction->value(), ' ', $header->currency, ', VAT ', $total->vatTotal ?? '-', PHP_EOL;
echo 'Payment:     ', $total->matchStatus->value, ', open ', $total->openBaseValue ?? '-', PHP_EOL;

foreach ($transaction->details as $line) {
    echo sprintf(
        '  %-6s %-6s %10s  %-4s %8s  %s',
        $line->debitCredit->value,
        $line->dim1->code,
        $line->value,
        $line->vatCode ?? '',
        $line->vatValue ?? '',
        $line->description ?? '',
    ), PHP_EOL;
}

foreach ($transaction->vat as $line) {
    echo sprintf(
        '  %-6s %-6s %10s  VAT %s on %s',
        $line->debitCredit->value,
        $line->dim1->code,
        $line->value,
        $line->vatCode,
        $line->vatTurnover ?? '-',
    ), PHP_EOL;
}

foreach ($total->matches as $set) {
    foreach ($set->lines as $match) {
        echo '  paid with ', $match->dayBook, ' ', $match->number, ' line ', $match->line, ': ', $match->matchValue,
            ' on ', $set->matchDate->format('Y-m-d'), PHP_EOL;
    }
}
