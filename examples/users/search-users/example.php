<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$users = $twinfield->users();

// Users whose code ends with _AI, by default `*` (all users).
$pattern = $argv[1] ?? '*_AI';
$page = $users->search($pattern)->inCode()->limit(10)->get();

echo $page->totalRows, ' user(s) with a code matching ', $pattern, PHP_EOL;

foreach ($page as $user) {
    echo '  ', str_pad($user->code, 16), ' ', $user->name, PHP_EOL;
}

// The default search leaves out some users; mutualoffices gives all users that share a company.
echo 'Default search: ', $users->search()->count(), ' users', PHP_EOL;
echo 'Sharing a company with you: ', $users->search()->withMutualCompanies()->count(), ' users', PHP_EOL;
