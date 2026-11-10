<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\User\UserNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the user of the access token.
$code = $argv[1] ?? $twinfield->userCode();

try {
    $user = $twinfield->users()->get($code);
} catch (UserNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$account = $user->account;
$companies = $user->companies;

echo 'Code:          ', $user->code, $user->isCurrentUser ? ' (you)' : '', PHP_EOL;
echo 'Name:          ', $user->name, ' (', $user->shortName ?? 'no short name', ')', PHP_EOL;
echo 'Email:         ', $user->email, PHP_EOL;
echo 'Status:        ', $user->status->value, PHP_EOL;
echo 'Type:          ', $user->type->value, ', ', $user->culture->value, PHP_EOL;
echo 'Role:          ', $user->role->code, ' (', $user->role->name, ', level ', $user->role->level, ')', PHP_EOL;
echo 'Invitation:    ', $user->invitationStatus->value, PHP_EOL;
echo 'SSO:           ', $account->singleSignOn->name, PHP_EOL;
echo 'Last logon:    ', $account->lastLogon?->format('Y-m-d H:i') ?? 'never', PHP_EOL;
echo 'Disabled now:  ', $account->isDisabledAt(new DateTimeImmutable()) ? 'yes' : 'no', PHP_EOL;
echo 'Companies:     ', $companies->restricted ? count($companies->companies) . ' companies' : 'all companies', PHP_EOL;

foreach (array_slice($companies->companies, 0, 3) as $company) {
    echo '               ', $company->code, ' ', $company->name, PHP_EOL;
}

echo 'Default:       ', $companies->defaultCompany?->code ?? 'chosen after logging in', PHP_EOL;
echo 'Access here:   ', $user->hasAccessTo((string) $twinfield->companyCode()) ? 'yes' : 'no',
    ' (', $twinfield->companyCode(), ')', PHP_EOL;
echo 'Week hours:    ', $user->time->weekHours ?? 'not set', PHP_EOL;
echo 'Changed:       ', $user->modified->format('Y-m-d H:i'), ' by ', $user->modifiedBy->code, PHP_EOL;
