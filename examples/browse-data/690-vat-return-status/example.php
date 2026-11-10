<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$year = (int) ($argv[1] ?? date('Y'));

$query = $twinfield->browse(BrowseCode::VatReturnStatus)
    ->columns(
        'fin.vat.decltype',
        'fin.vat.declyear',
        'fin.vat.timeframe',
        'fin.vat.statusname',
        'fin.vat.sent',
    )
    ->equal('fin.vat.declyear', $year);

$result = $query->get();

echo count($result), ' return(s) in ', $year, PHP_EOL;

foreach ($result as $index => $row) {
    $sent = $row->nullableString('fin.vat.sent') === null ? null : $row->dateTime('fin.vat.sent');

    echo sprintf(
        '[%d/%d] %s %d %s: %s%s',
        $index + 1,
        count($result),
        $row->string('fin.vat.decltype'),
        $row->int('fin.vat.declyear'),
        $row->displayName('fin.vat.timeframe') ?? $row->string('fin.vat.timeframe'),
        $row->string('fin.vat.statusname'),
        $sent === null ? '' : ', sent ' . $sent->format('Y-m-d H:i'),
    ), PHP_EOL;
}
