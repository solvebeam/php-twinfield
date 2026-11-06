<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activities->create(ActivityInput::create($code, 'Example activity'));

try {
    $activity = $activities->get($code);
    echo 'Before: ', $activity->name, PHP_EOL;

    $activities->update(
        $activity->edit()
            ->name('Example activity (updated)')
            ->validTill(new DateTimeImmutable('2030-12-31')),
    );

    $activity = $activities->get($code);
    echo 'After:  ', $activity->name, ', valid till ', $activity->projects->validTill?->format('Y-m-d'), PHP_EOL;
} finally {
    $activities->delete($code);
}
