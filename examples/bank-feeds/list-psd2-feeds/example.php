<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$warnFrom = new DateTimeImmutable('+30 days');

foreach ($twinfield->bankFeeds()->psd2()->inAllCompanies()->all() as $feed) {
    echo sprintf(
        '%s %s %s: consent since %s, expires %s%s',
        $feed->account->companyCode,
        $feed->account->companyName,
        $feed->account->iban,
        $feed->consentOn->format('Y-m-d'),
        $feed->expiresOn->format('Y-m-d'),
        $feed->expiresOn < $warnFrom ? ' (renew soon)' : '',
    ), PHP_EOL;
}
