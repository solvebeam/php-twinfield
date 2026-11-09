<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\User\UserAlreadyExistsException;
use SolveBeam\Twinfield\User\UserCulture;
use SolveBeam\Twinfield\User\UserInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// Creating a user can cost money, so the code and email address must be given.
[, $code, $email] = $argv + [null, null, null];

if ($code === null || $email === null) {
    exit('Usage: php examples/users/create-user/example.php CODE EMAIL (a new user can cost money)' . PHP_EOL);
}

// Same type and role as yourself, with access to the company of the client only.
$me = $twinfield->users()->current();

try {
    $user = $twinfield->users()->create(
        UserInput::create($code, 'Example user')
            ->email($email)
            ->type($me->type)
            ->culture(UserCulture::English)
            ->role($me->role)
            ->companies((string) $twinfield->companyCode())
            ->defaultCompany($twinfield->companyCode())
            ->weekHours(40)
            ->invite(),
    );
} catch (UserAlreadyExistsException $exception) {
    echo $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $user->code, ' (', $user->email, '), invitation: ', $user->invitationStatus->value, PHP_EOL;
echo 'Companies: ', implode(', ', $user->companies->codes()), PHP_EOL;
