<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '-7 days');
$changes = $twinfield->customers()->changes($since);
$total = $changes->limit(1)->get()->filteredTotal;

echo $total, ' customer(s) changed since ', $since->format('Y-m-d H:i'), PHP_EOL;

foreach ($changes->all() as $index => $change) {
    echo sprintf(
        '[%d/%d] %s %s: %s, %s',
        $index + 1,
        $total,
        $change->modified->format('Y-m-d H:i'),
        $change->name,
        $change->status->value,
        $change->id,
    ), PHP_EOL;
}
