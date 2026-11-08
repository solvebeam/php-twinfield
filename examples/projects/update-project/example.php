<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Project\ProjectInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$projects->create(ProjectInput::create($code, 'Example project'));

try {
    $project = $projects->get($code);
    echo 'Before: ', $project->name, PHP_EOL;

    $projects->update(
        $project->edit()
            ->name('Example project (updated)')
            ->validTill(new DateTimeImmutable('2030-12-31')),
    );

    $project = $projects->get($code);
    echo 'After:  ', $project->name, ', valid till ', $project->projects->validTill?->format('Y-m-d'), PHP_EOL;
} finally {
    $projects->delete($code);
}
