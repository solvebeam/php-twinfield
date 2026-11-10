<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Supplier\MeansOfPayment;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first supplier.
$code = $argv[1] ?? $twinfield->suppliers()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no suppliers.' . PHP_EOL);

try {
    $supplier = $twinfield->suppliers()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$financials = $supplier->financials;

echo 'Code:          ', $supplier->code, PHP_EOL;
echo 'Name:          ', $supplier->name, PHP_EOL;
echo 'Status:        ', $supplier->status->value, $supplier->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Payment term:  ', $financials->dueDays, ' days', PHP_EOL;
echo 'Paid by:       ', match ($financials->meansOfPayment) {
    MeansOfPayment::PaymentFile => 'credit transfer, ' . $financials->payCode,
    MeansOfPayment::Cheques => 'cheque',
    MeansOfPayment::None => 'not by Twinfield',
}, PHP_EOL;
echo 'Our number:    ', $financials->relationsReference ?? '-', PHP_EOL;
echo 'VAT code:      ', $financials->vatCode ?? '-', PHP_EOL;
echo 'Account:       ', $financials->balanceAccount, PHP_EOL;
echo 'Remittance:    ',
    $supplier->remittanceAdvice->email ?? $supplier->remittanceAdvice?->sendType->name ?? '-', PHP_EOL;
echo 'VAT number:    ', $supplier->vatNumber() ?? '-', PHP_EOL;
echo 'CoC number:    ', $supplier->chamberOfCommerceNumber() ?? '-', PHP_EOL;

foreach ($supplier->addresses as $address) {
    echo 'Address ', $address->id, ':     ', $address->type->value, $address->default ? ' (default)' : '', ', ',
        implode(', ', array_filter([$address->field2, $address->postcode, $address->city, $address->country])), PHP_EOL;
}

foreach ($supplier->banks as $bank) {
    echo 'Bank ', $bank->id, ':        ', $bank->iban ?? $bank->accountNumber ?? '-',
        $bank->default ? ' (default)' : '', $bank->blocked === true ? ' (blocked)' : '', PHP_EOL;
}

foreach ($supplier->postingRules as $rule) {
    foreach ($rule->lines as $line) {
        echo 'Posting rule:  ', $line->generalLedger, $line->costCenter === null ? '' : ' / ' . $line->costCenter,
            ', VAT ', $line->vatCode ?? '-', $line->ratio === null ? '' : ', ' . $line->ratio . '%', PHP_EOL;
    }
}

foreach ($supplier->paymentConditions as $condition) {
    echo 'Discount:      ', $condition->discountPercentage, '% within ', $condition->discountDays, ' days', PHP_EOL;
}
