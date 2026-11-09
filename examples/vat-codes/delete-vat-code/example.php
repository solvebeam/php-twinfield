<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\VatCode\VatAccountInput;
use SolveBeam\Twinfield\VatCode\VatCodeInput;
use SolveBeam\Twinfield\VatCode\VatPercentageInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$vatCodes = $twinfield->vatCodes();
$code = $argv[1] ?? Example::unusedVatCode($twinfield);

$vatCodes->create(
    VatCodeInput::sales($code, 'Example VAT code')->percentage(
        VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
            ->account(VatAccountInput::vat(Example::vatPayableAccount($twinfield))),
    ),
);

$result = $vatCodes->delete($code);

echo match ($result) {
    DeleteResult::Deleted => "Deleted {$code}.",
    DeleteResult::Hidden => "{$code} is in use, so Twinfield hid it instead.",
}, PHP_EOL;

echo 'Status now: ', $vatCodes->find($code)?->status->value ?? 'gone', PHP_EOL;
