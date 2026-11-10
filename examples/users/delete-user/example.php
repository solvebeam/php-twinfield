<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\User\UserDeleteReason;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Deleting someone's login is never done by default: the code must be given.
$code = $argv[1] ?? exit('Usage: php examples/users/delete-user/example.php CODE' . PHP_EOL);

if ($code === $twinfield->userCode()) {
    exit('That is the user of your access token.' . PHP_EOL);
}

$users = $twinfield->users();
$result = $users->delete($code, UserDeleteReason::NoLongerOperative, 'Deleted by the twinfield-php example');

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $users->get($code)->status->value, PHP_EOL;

// To undo: $users->activate($code);
