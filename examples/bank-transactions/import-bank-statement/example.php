<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BankTransaction\BankTransactionInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Transaction\Amount;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Your chart of accounts and relations: change these to match your administration.
$bankBook = 'BNK';
$bankAccount = '1010';
$accounts = ['receivable' => '1300', 'payable' => '1600', 'bankCosts' => '4500', 'suspense' => '2300'];
$customers = ['NL91ABNA0417164300' => '1000', 'DE89370400440532013000' => '1001'];
$suppliers = ['NL20INGB0001234567' => '2000'];

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

$date = new DateTimeImmutable($lines[0][0] ?? 'today');

$input = BankTransactionInput::create($bankBook, $bankAccount, $date)
    ->statement((int) $statementNumber, startValue: Amount::fromString($opening))
    ->description('Statement ' . $statementNumber);

echo 'Statement ', $statementNumber, ': ', count($lines), ' lines', PHP_EOL;

foreach ($lines as $index => $line) {
    [$date, $amount, $name, $iban, $description] = $line + ['', '', '', '', ''];
    $amount = Amount::fromString($amount);
    $invoices = preg_match_all('~\b20\d{2}-\d{4}\b~', $description, $matches) > 0 ? $matches[0] : [];
    // One invoice: book it on that invoice. More invoices: leave the invoice empty and match later.
    $invoice = count($invoices) === 1 ? $invoices[0] : null;
    $text = mb_substr($name . ' ' . $description, 0, 40);

    [$action, $input] = match (true) {
        isset($customers[$iban]) && !$amount->isNegative() => [
            sprintf('receive %s customer %s', $accounts['receivable'], $customers[$iban])
                . ($invoice === null ? '' : ", invoice {$invoice}"),
            $input->receive(
                $accounts['receivable'],
                $amount,
                customer: $customers[$iban],
                invoice: $invoice,
                description: $text,
            ),
        ],
        isset($suppliers[$iban]) && $amount->isNegative() => [
            sprintf('pay %s supplier %s', $accounts['payable'], $suppliers[$iban]),
            $input->pay($accounts['payable'], $amount->absolute(), supplier: $suppliers[$iban], description: $text),
        ],
        $iban === '' && $amount->isNegative() => [
            sprintf('pay %s bank costs', $accounts['bankCosts']),
            $input->pay($accounts['bankCosts'], $amount->absolute(), description: $text),
        ],
        default => [
            ($amount->isNegative() ? 'unknown: pay from' : 'unknown: receive on')
                . ' suspense account ' . $accounts['suspense'],
            $amount->isNegative()
                ? $input->pay($accounts['suspense'], $amount->absolute(), description: $text)
                : $input->receive($accounts['suspense'], $amount, description: $text),
        ],
    };

    echo sprintf(
        '[%d/%d] %s %10s  %-18s → %s',
        $index + 1,
        count($lines),
        $date,
        ($amount->isNegative() ? '' : '+') . $amount,
        $name,
        $action,
    ), PHP_EOL;
}

if (!$input->closeValue()?->equals(Amount::fromString($closing))) {
    echo 'The lines do not add up: closing balance ', $input->closeValue(), ', statement says ', $closing, '.', PHP_EOL;

    exit(1);
}

echo 'Closing balance ', $input->closeValue(), ' matches the statement.', PHP_EOL;

if (!in_array('--save', $argv ?? [], true)) {
    echo PHP_EOL, 'Dry run; add --save to create it as provisional transaction:', PHP_EOL, PHP_EOL;
    echo Example::xml($input->toXml($twinfield->companyCode() ?? '')), PHP_EOL;

    exit(0);
}

$transaction = $twinfield->bankTransactions()->create($input);

echo 'Created provisional transaction ', $transaction->dayBook(), ' ', $transaction->number(), PHP_EOL;
