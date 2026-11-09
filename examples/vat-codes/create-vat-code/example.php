<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\VatCode\VatAccountInput;
use SolveBeam\Twinfield\VatCode\VatCodeInput;
use SolveBeam\Twinfield\VatCode\VatPercentageInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$vatCodes = $twinfield->vatCodes();
$code = $argv[1] ?? Example::unusedVatCode($twinfield);

// The VAT payable account of the company, e.g. 1530.
$account = Example::vatPayableAccount($twinfield);

$vatCode = $vatCodes->create(
    VatCodeInput::sales($code, 'Example VAT 21%')
        ->shortName('Example 21%')
        ->percentage(
            VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
                ->account(VatAccountInput::vat($account)->group('NL', 'NL1A')),
        ),
);

echo 'Created ', $vatCode->code, ' ', $vatCode->name, ' (', $vatCode->type->value, '): ';
echo $vatCode->latestPercentage()->percentage, '% from ', $vatCode->latestPercentage()->from->format('Y-m-d'), PHP_EOL;

// Clean up: delete the example VAT code again.
$vatCodes->delete($vatCode->code);

echo 'Deleted ', $vatCode->code, PHP_EOL;
