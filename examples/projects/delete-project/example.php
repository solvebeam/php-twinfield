<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Project\ProjectInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$projects->create(ProjectInput::create($code, 'Example project'));

$result = $projects->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $projects->get($code)->status->value, PHP_EOL;
