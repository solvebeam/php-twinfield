<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional search pattern from the command line, e.g. `F*`.
$pattern = $argv[1] ?? '*';

foreach ($twinfield->salesInvoices()->invoiceTypes($pattern)->all() as $type) {
    echo $type->code, '  ', $type->name, PHP_EOL;
}
