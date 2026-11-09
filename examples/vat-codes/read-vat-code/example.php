<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\VatCode\VatCodeNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first VAT code.
$code = $argv[1] ?? $twinfield->vatCodes()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no VAT codes.' . PHP_EOL);

try {
    $vatCode = $twinfield->vatCodes()->get($code);
} catch (VatCodeNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

echo 'Code:      ', $vatCode->code, PHP_EOL;
echo 'Name:      ', $vatCode->name, PHP_EOL;
echo 'Type:      ', $vatCode->type->value, PHP_EOL;
echo 'Status:    ', $vatCode->status->value, PHP_EOL;

foreach ($vatCode->percentages as $percentage) {
    echo 'From ', $percentage->from->format('Y-m-d'), ': ', $percentage->percentage, '% ', $percentage->name, PHP_EOL;

    foreach ($percentage->accounts as $account) {
        echo '  ', $account->percentage, '% on ', $account->generalLedgerAccount->code;
        echo ' (', $account->generalLedgerAccount->name ?? '', ')';
        echo $account->group === null ? '' : ', VAT group ' . $account->group->code, PHP_EOL;
    }
}

$today = new DateTimeImmutable('today');

echo 'Today:     ', $vatCode->percentageOn($today)->percentage ?? 'no', '% VAT', PHP_EOL;
echo 'VAT on 100.00 today: ', $vatCode->vatOn('100.00', $today), PHP_EOL;
