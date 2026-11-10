<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\TimeEntry\TimeEntryInput;
use SolveBeam\Twinfield\TimeEntry\TimeEntryXmlWriter;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// E.g. `JDOE P0099 A010`: the user the time is for, a project and an activity of the company.
$arguments = array_values(array_filter(
    array_slice($argv ?? [], 1),
    static fn (string $argument): bool => $argument !== '--save',
));
[$user, $project, $activity] = array_pad($arguments, 3, null);

if ($user === null || $project === null || $activity === null) {
    echo 'Usage: php example.php <user> <project> <activity> [--save]', PHP_EOL;

    exit(0);
}

// Four units of time and 12.5 kilometres on one day. Twinfield takes the rates and whether the
// lines are billable from the project and the activity, unless you pass them.
$input = TimeEntryInput::direct($user, new DateTimeImmutable('today'), $project, $activity)
    ->time(240, description: 'Implementation')
    ->quantity('12.5');

if (!in_array('--save', $argv ?? [], true)) {
    echo 'Dry run; add --save to save the time entry (it can\'t be deleted through the API):', PHP_EOL, PHP_EOL;
    echo Example::xml(TimeEntryXmlWriter::request((string) $twinfield->companyCode(), $input)), PHP_EOL;

    exit(0);
}

try {
    $entry = $twinfield->timeEntries()->save($input);
} catch (ProcessXmlException $exception) {
    foreach ($exception->result->messages() as $message) {
        echo $message->type->value, ' at ', $message->path, ': ', $message->text, PHP_EOL;
    }

    exit(1);
}

echo 'Saved: ', $entry->type->value, ' ', $entry->date->format('Y-m-d'), ' ', $entry->user->code, ' on ',
    $entry->project?->code ?? '-', ' / ', $entry->activity?->code ?? '-', PHP_EOL;

foreach ($entry->timeLines as $line) {
    echo '  ', $line->id, ' time ', $line->duration, ' at ', $line->rate->code, ': ', $line->internalValue,
        ' internal, ', $line->externalValue, ' external ', $line->currency,
        $line->billable ? ', billable' : '', PHP_EOL;
}

foreach ($entry->quantityLines as $line) {
    echo '  ', $line->id, ' quantity ', $line->quantity, ' at ', $line->rate->code, ': ', $line->internalValue,
        ' internal, ', $line->externalValue, ' external ', $line->currency,
        $line->billable ? ', billable' : '', PHP_EOL;
}
