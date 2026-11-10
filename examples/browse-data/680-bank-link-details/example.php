<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$query = $twinfield->browse(BrowseCode::BankLinkDetails)
    ->columns(
        'fin.bnk.transactiontype',
        'fin.bnk.iban',
        'fin.bnk.dim1',
        'fin.bnk.dim1name',
        'fin.bnk.banklinkstatus',
    );

$result = $query->get();

echo count($result), ' bank book(s)', PHP_EOL;

foreach ($result as $index => $row) {
    $account = $row->nullableString('fin.bnk.dim1');

    echo sprintf(
        '[%d/%d] %s IBAN %s, account %s: bank link %s',
        $index + 1,
        count($result),
        $row->string('fin.bnk.transactiontype'),
        $row->string('fin.bnk.iban'),
        $account === null ? '-' : $account . ' ' . ($row->nullableString('fin.bnk.dim1name') ?? ''),
        $row->string('fin.bnk.banklinkstatus'),
    ), PHP_EOL;
}
