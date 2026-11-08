<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Rate\RateType;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Optional type and name pattern from the command line, e.g. `quantity '*km*'`.
$type = RateType::tryFrom($argv[1] ?? 'time')
    ?? exit('Type must be time or quantity.' . PHP_EOL);
$pattern = $argv[2] ?? '*';

$search = $twinfield->rates()->search($pattern)->inName()->rateType($type);

echo $search->count(), ' ', $type->value, ' rates match ', $pattern, PHP_EOL;

foreach ($search->limit(10)->get() as $rate) {
    echo $rate->code, '  ', $rate->name, PHP_EOL;
}
