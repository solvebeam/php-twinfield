<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$days = (int) ($argv[1] ?? 30);
$dayBook = $argv[2] ?? null;
$from = new DateTimeImmutable(sprintf('-%d days', $days));

$transactions = $twinfield->deletedTransactions()->list(dayBook: $dayBook, from: $from);

echo 'Company ', $twinfield->companyCode(), PHP_EOL;
echo count($transactions), ' transaction(s) deleted since ', $from->format('Y-m-d');
echo $dayBook === null ? '' : ' in day book ' . $dayBook, PHP_EOL;

foreach ($transactions as $index => $transaction) {
    echo sprintf(
        '[%d/%d] %s %d (dated %s) deleted %s by %s: %s',
        $index + 1,
        count($transactions),
        $transaction->dayBook,
        $transaction->number,
        $transaction->transactionDate->format('Y-m-d'),
        $transaction->deletionDate->format('Y-m-d H:i'),
        $transaction->user,
        $transaction->reason,
    ), PHP_EOL;
}
