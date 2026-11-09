<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The user code comes from the access token validation, no extra request.
echo 'Logged in as:  ', $twinfield->userCode(), PHP_EOL;

// One read of that user.
$me = $twinfield->users()->current();

echo 'Name:          ', $me->name, ' <', $me->email, '>', PHP_EOL;
echo 'Role:          ', $me->role->name, PHP_EOL;
echo 'Companies:     ', $me->companies->restricted ? implode(', ', $me->companies->codes()) : 'all companies', PHP_EOL;
