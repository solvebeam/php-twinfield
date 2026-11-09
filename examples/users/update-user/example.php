<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$users = $twinfield->users();

// The code from the command line, or else the user of the access token.
$user = $users->get($argv[1] ?? $twinfield->userCode());

echo 'Week hours before: ', $user->time->weekHours ?? 'not set', PHP_EOL;

// edit() keeps all other settings. For a user restricted to some companies, grantAccessTo()
// and revokeAccessTo() change the list, e.g. ->grantAccessTo('1001').
$updated = $users->update($user->edit()->weekHours(32));

echo 'Week hours after:  ', $updated->time->weekHours, PHP_EOL;

// Restore the original value.
$users->update($user->edit());

echo 'Restored:          ', $users->get($user->code)->time->weekHours ?? 'not set', PHP_EOL;
