<?php

declare(strict_types=1);

use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DimensionReference;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The IBAN of your bank book: Twinfield finds the bank book by it.
$iban = 'NL44RABO0123456789';

$handle = fopen($argv[1] ?? __DIR__ . '/statement.csv', 'r') ?: exit('Cannot read the statement.' . PHP_EOL);
$rows = [];

while (($row = fgetcsv($handle, escape: '')) !== false) {
    if ($row !== [null]) {
        $rows[] = array_map(strval(...), $row);
    }
}

// Row 1 and 2: the statement; row 3: the column names; then one row per bank line.
[$statementNumber, $opening, $closing] = $rows[1] + ['', '', ''];
$lines = array_slice($rows, 3);

// The input checks that the opening balance plus the lines is the closing balance.
$input = ElectronicBankStatementInput::forIban($iban)
    ->date(new DateTimeImmutable($lines[0][0] ?? 'today'))
    ->number(
        (int) $statementNumber,
        startValue: Amount::fromString($opening),
        closeValue: Amount::fromString($closing),
    );

echo 'Statement ', $statementNumber, ': ', count($lines), ' lines', PHP_EOL;

foreach ($lines as $index => $line) {
    [$date, $amount, $name, $contraIban, $description] = $line + ['', '', '', '', ''];
    $amount = Amount::fromString($amount);
    $contraIban = $contraIban === '' ? null : $contraIban;

    // No rules here: Twinfield allocates each line with the allocation rules of the bank book.
    $input = $amount->isNegative()
        ? $input->pay($amount->absolute(), $name . ' ' . $description, contraIban: $contraIban)
        : $input->receive($amount, $name . ' ' . $description, contraIban: $contraIban);

    echo sprintf(
        '[%d/%d] %s %10s  %-18s %s',
        $index + 1,
        count($lines),
        $date,
        ($amount->isNegative() ? '' : '+') . $amount,
        $name,
        $description,
    ), PHP_EOL;
}

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to import it (a statement cannot be changed afterwards):', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$statement = $twinfield->electronicBankStatements()->create($input);

echo 'Imported statement ', $statement->number, ' in bank book ', $statement->bankBook, PHP_EOL;

foreach ($statement->lines as $index => $line) {
    $dimensions = array_map(
        static fn (DimensionReference $dimension): string
            => $dimension->code . ($dimension->name === null ? '' : " ({$dimension->name})"),
        $line->dimensions,
    );

    echo sprintf(
        '[%d/%d] %10s %-40s → %s',
        $index + 1,
        count($statement->lines),
        $line->signedValue(),
        $line->description,
        implode(', ', $dimensions),
    ), PHP_EOL;
}
