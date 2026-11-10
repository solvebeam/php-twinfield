<?php

declare(strict_types=1);

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$type = $argv[1] ?? null;

$query = $twinfield->browse(BrowseCode::HierarchyAccess)
    ->columns(
        'hie.xss.hierarchy',
        'hie.xss.hierarchyname',
        'hie.xss.hierarchyhidden',
        'hie.xss.role',
        'hie.xss.user',
        'hie.xss.type',
    );

if ($type !== null) {
    $query = $query->equal('hie.xss.type', $type);
}

$result = $query->get();

echo count($result), ' access right(s)', $type === null ? '' : ' of type ' . $type, PHP_EOL;

foreach ($result as $index => $row) {
    $user = $row->nullableString('hie.xss.user');

    echo sprintf(
        '[%d/%d] %s %s%s: %s %s',
        $index + 1,
        count($result),
        $row->string('hie.xss.hierarchy'),
        $row->nullableString('hie.xss.hierarchyname') ?? '',
        $row->string('hie.xss.hierarchyhidden') === 'true' ? ' (hidden)' : '',
        $user === null ? 'role ' . $row->string('hie.xss.role') : 'user ' . $user,
        $row->string('hie.xss.type'),
    ), PHP_EOL;
}
