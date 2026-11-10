<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Office\OfficeNotFoundException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

try {
    // The code from the command line, or else the office of the client.
    $office = isset($argv[1]) ? $twinfield->offices()->get($argv[1]) : $twinfield->offices()->current();
} catch (OfficeNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$general = $office->general;
$vat = $office->vatManagement;
$dimensions = $office->systemDimensions;

echo 'Office:          ', $office->code, ' ', $office->name, PHP_EOL;
echo 'Type:            ', $general->type->name, $general->demo ? ' (demo)' : '', PHP_EOL;
echo 'Currency:        ', $general->baseCurrency->code, PHP_EOL;
echo 'VAT number:      ', $general->vatNumber ?? '-', PHP_EOL;
echo 'CoC number:      ', $general->cocNumber ?? '-', PHP_EOL;
echo 'Address:         ', $general->address->field1 ?? '-', ', ', $general->address->city ?? '-', PHP_EOL;
echo 'Last closed:     ', $general->lastClosedYear ?? '-', PHP_EOL;
echo 'Tax group:       ', $vat->taxGroup->code ?? '-', PHP_EOL;
echo 'VAT period:      ', $vat->declarationTimeframe, ', starting quarter ', $vat->startingQuarter, PHP_EOL;
echo 'Receivable:      ', $dimensions->accountsReceivable->code ?? '-', ' ',
    $dimensions->accountsReceivable?->name, PHP_EOL;
echo 'Payable:         ', $dimensions->accountsPayable->code ?? '-', ' ', $dimensions->accountsPayable?->name, PHP_EOL;
echo 'Customers:       dimension type ', $office->systemDimensionTypes->accountsReceivable->code, PHP_EOL;
echo 'Intercompany:    ', $office->intercompany->status->name, PHP_EOL;

foreach ($office->intercompany->outgoing as $relation) {
    echo '  to ', $relation->office->code, ' on ', $relation->dimension1->code ?? '-', PHP_EOL;
}

foreach ($office->intercompany->incoming as $relation) {
    echo '  from ', $relation->office->code, ' on ', $relation->dimension1->code ?? '-', PHP_EOL;
}

echo 'Locked:          ',
    implode(', ', array_map(static fn ($field) => $field->name, $office->lockedFields)) ?: '-', PHP_EOL;
echo 'Modified:        ', $office->modified->format('Y-m-d H:i'), ' by ', $office->user->code, PHP_EOL;
