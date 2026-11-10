<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$query = $twinfield->browse(BrowseCode::BankLinkDetailsV2)
    ->columns(
        'fin.bnk.transactiontype',
        'fin.bnk.iban',
        'fin.bnk.currency',
        'fin.bnk.accountintegrationtype',
        'fin.bnk.payments',
        'fin.bnk.directdebits',
        'fin.bnk.consentexpirydate',
    )
    ->equal('fin.bnk.accountstatus', 'active');

$result = $query->get();

echo count($result), ' active bank book(s)', PHP_EOL;

foreach ($result as $index => $row) {
    $consent = $row->nullableDate('fin.bnk.consentexpirydate');

    echo sprintf(
        '[%d/%d] %s IBAN %s %s: account information %s, payments %s, direct debits %s, consent until %s',
        $index + 1,
        count($result),
        $row->string('fin.bnk.transactiontype'),
        $row->string('fin.bnk.iban'),
        $row->string('fin.bnk.currency'),
        $row->string('fin.bnk.accountintegrationtype'),
        $row->string('fin.bnk.payments'),
        $row->string('fin.bnk.directdebits'),
        $consent?->format('Y-m-d') ?? '-',
    ), PHP_EOL;
}
