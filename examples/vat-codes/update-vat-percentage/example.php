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

// An example VAT code of 6% to change, deleted again at the end.
$vatCode = $vatCodes->create(
    VatCodeInput::sales($code, 'Example VAT low')->percentage(
        VatPercentageInput::from(new DateTimeImmutable('2018-01-01'), '6')
            ->name('Example VAT 6%')
            ->account(VatAccountInput::vat(Example::vatPayableAccount($twinfield))->group('NL', 'NL1B')),
    ),
);

// 9% from 2019, on the same accounts as the 6%.
$vatCode = $vatCodes->update(
    $vatCode->edit()->newPercentageFrom(new DateTimeImmutable('2019-01-01'), '9', 'Example VAT 9%'),
);

foreach ($vatCode->percentages as $percentage) {
    echo $percentage->from->format('Y-m-d'), ': ', $percentage->percentage, '% ', $percentage->name, PHP_EOL;
}

echo 'VAT on 100.00 in 2018: ', $vatCode->vatOn('100.00', new DateTimeImmutable('2018-06-01')), PHP_EOL;
echo 'VAT on 100.00 in 2019: ', $vatCode->vatOn('100.00', new DateTimeImmutable('2019-06-01')), PHP_EOL;

$vatCodes->delete($vatCode->code);

echo 'Deleted ', $vatCode->code, PHP_EOL;
