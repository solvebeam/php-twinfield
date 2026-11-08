<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$pattern = $argv[1] ?? '*';

$page = $twinfield->projects()
    ->search($pattern)
    ->inName()
    ->modifiedSince(new DateTimeImmutable('2010-01-01'))
    ->limit(10)
    ->get();

echo $page->totalRows, ' projects with a name like "', $pattern, '", first ', count($page), ':', PHP_EOL;

foreach ($page as $project) {
    echo $project->code, '  ', $project->name, PHP_EOL;
}
