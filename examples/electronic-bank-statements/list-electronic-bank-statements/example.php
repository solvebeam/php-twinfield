<?php

declare(strict_types=1);

use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocation;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$days = (int) ($argv[1] ?? 7);
$from = new DateTimeImmutable(sprintf('-%d days', $days));
$to = new DateTimeImmutable('today');

$statements = $twinfield->electronicBankStatements()->list($from, $to);

echo 'Company ', $twinfield->companyCode(), PHP_EOL;
echo count($statements), ' statement(s) dated ', $from->format('Y-m-d'), ' to ', $to->format('Y-m-d'), PHP_EOL;

foreach ($statements as $index => $statement) {
    echo sprintf(
        '[%d/%d] %s %d of %s: %s to %s, %d line(s), %s',
        $index + 1,
        count($statements),
        $statement->bankBook,
        $statement->number,
        $statement->date->format('Y-m-d'),
        $statement->openingBalance,
        $statement->closingBalance,
        count($statement->lines),
        $statement->isPosted() ? 'posted as ' . $statement->transactionNumber : 'not posted',
    ), PHP_EOL;

    foreach ($statement->lines as $line) {
        $allocations = array_map(
            static fn (BankStatementAllocation $allocation): string => sprintf(
                '%s %s %s',
                $allocation->type->value,
                implode('/', $allocation->dimensions),
                $allocation->signedAmount(),
            ),
            $line->allocations,
        );

        echo sprintf(
            '  %3d %10s  %-30.30s  %s%s',
            $line->lineId,
            $line->amount,
            $line->contraAccountName ?? $line->description ?? '',
            implode(', ', $allocations),
            $line->recognition === null ? '' : ' (' . $line->recognition->value . ')',
        ), PHP_EOL;
    }
}
