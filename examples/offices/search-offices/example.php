<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*B.V.*';

$search = $twinfield->offices()->search($pattern)->inName();

$page = $search->limit(5)->get();

echo $page->totalRows, ' offices with a name like "', $pattern, '", first ', count($page), ':', PHP_EOL;

foreach ($page as $office) {
    echo $office->code, '  ', $office->name, PHP_EOL;
}

// Offices that can join a fiscal unity: not in a tax group, no template, no VAT declaration in progress.
echo $search->withoutTaxGroup()->count(), ' of them are not in a tax group.', PHP_EOL;
