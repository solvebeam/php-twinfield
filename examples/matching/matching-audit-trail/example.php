<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '-30 days');

foreach ($twinfield->matching()->auditTrailSince($since) as $event) {
    echo sprintf(
        '%s %s match %d on %s by %s',
        $event->logTimestamp->format('Y-m-d H:i'),
        $event->eventType->value,
        $event->matchNumber,
        $event->dimensionCode,
        $event->userCode,
    ), PHP_EOL;

    foreach ($event->lines as $line) {
        echo '  ', $line->dayBook, ' ', $line->number, ' line ', $line->lineId, ': ', $line->matchedAmount, PHP_EOL;
    }
}
