<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Activity\ActivityInput;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$activities = $twinfield->activities();
$code = $argv[1] ?? Example::unusedActivityCode($twinfield);

$activities->create(ActivityInput::create($code, 'Example activity'));

$result = $activities->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $activities->get($code)->status->value, PHP_EOL;
