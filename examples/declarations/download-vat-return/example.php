<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Declaration\DeclarationSummary;
use SolveBeam\Twinfield\Declaration\DeclarationType;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$declarations = $twinfield->declarations();

// The last VAT return of last year, of this company or else of the organisation.
$year = (int) date('Y') - 1;
$isVatReturn = static fn (DeclarationSummary $return): bool => $return->type === DeclarationType::VatTurnover;
$returns = array_filter($declarations->list(limit: 1000, year: $year), $isVatReturn)
    ?: array_filter($declarations->listForOrganisation(limit: 10000, year: $year), $isVatReturn);

if ($returns === []) {
    echo 'No VAT returns in ', $year, '.', PHP_EOL;

    exit;
}

$return = end($returns);

echo $return->name, ' of company ', $return->company->code, ' (', $return->status->description, ')', PHP_EOL;

$file = sprintf('%s/vat-return-%d.xbrl', sys_get_temp_dir(), $return->id);
file_put_contents($file, $declarations->vatReturnXbrl($return->id));

echo 'XBRL saved to ', $file, ' (', filesize($file), ' bytes)', PHP_EOL;
echo 'Payment reference: ', $declarations->vatReturnPaymentReference($return->id) ?? 'none', PHP_EOL;
