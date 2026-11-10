<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// One search over all companies the user has access to (Finder option company=*).
$page = $twinfield->vatCodes()->search()->inAllCompanies()->limit(10)->get();

echo "{$page->totalRows} VAT codes in all companies, first ", count($page), ':', PHP_EOL;

foreach ($page as $summary) {
    echo $summary->company->code, ' ', $summary->code, ' ', $summary->name, PHP_EOL;
}
