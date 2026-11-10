<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$date = new DateTimeImmutable($argv[1] ?? 'today');
$accounts = [];

foreach ($twinfield->vatCodes()->searchDetails()->orderByCode()->all() as $vatCode) {
    $rate = $vatCode->rateOn($date);

    echo $vatCode->code, ' ', $vatCode->name, ' (', $vatCode->type->value, '): ';
    echo $rate === null
        ? 'no rate on ' . $date->format('Y-m-d')
        : $rate->percentage . '% since ' . $rate->startDate->format('Y-m-d');
    echo PHP_EOL;

    foreach ($rate->allocationRules ?? [] as $rule) {
        if ($rule->generalLedgerAccountId === null) {
            continue;
        }

        $id = $rule->generalLedgerAccountId;
        $accounts[$id] ??= $twinfield->ledgerAccounts()->get($id)->code;

        echo '  ', $rule->percentage, '% of the VAT on ', $accounts[$id];
        echo $rule->isVatLine ? ' (VAT line)' : '', PHP_EOL;
    }
}
