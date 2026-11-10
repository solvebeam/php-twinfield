<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\ChangeMode;
use SolveBeam\Twinfield\Dimension\Quantity;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Project\ProjectInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$projects = $twinfield->projects();
$code = $argv[1] ?? Example::unusedProjectCode($twinfield);

$project = $projects->create(
    ProjectInput::create($code, 'Example project: website redesign and API documentation')
        ->shortName('Example')
        ->invoiceDescription('Created by the twinfield-php examples')
        ->validFrom(new DateTimeImmutable('first day of january this year'))
        ->validTill(new DateTimeImmutable('last day of december this year'))
        ->billable(true, ChangeMode::Disallow)
        ->quantity(Quantity::create('KM')->billable(false)),
);

echo 'Created ', $project->code, ' ', $project->name, ' (', $project->uid, ')', PHP_EOL;

// Clean up: delete the example project again.
$projects->delete($project->code);

echo 'Deleted ', $project->code, PHP_EOL;
