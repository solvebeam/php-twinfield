<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$periods = $twinfield->periods();
$years = $periods->years();

echo 'Company ', $twinfield->companyCode(), PHP_EOL;

if ($years === []) {
    echo 'The company has no years yet.', PHP_EOL;

    exit;
}

$year = max($years);

$nameOf = static function (int $number) use ($periods, $year): string {
    foreach ($periods->list($year) as $period) {
        if ($period->number === $number) {
            return $period->name;
        }
    }

    throw new RuntimeException("Period {$number} of {$year} not found.");
};

$original = $nameOf(1);
echo 'Before:   ', $original, PHP_EOL;

try {
    $periods->changeName($year, 1, $original . ' (renamed)');
    echo 'Renamed:  ', $nameOf(1), PHP_EOL;
} finally {
    $periods->changeName($year, 1, $original);
    echo 'Restored: ', $nameOf(1), PHP_EOL;
}
