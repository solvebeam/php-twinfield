<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

// The code from the command line, or else the first customer.
$code = $argv[1] ?? $twinfield->customers()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no customers.' . PHP_EOL);

try {
    $customer = $twinfield->customers()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$financials = $customer->financials;
$credit = $customer->creditManagement;

echo 'Code:          ', $customer->code, PHP_EOL;
echo 'Name:          ', $customer->name, PHP_EOL;
echo 'Status:        ', $customer->status->value, $customer->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Website:       ', $customer->website ?? '-', PHP_EOL;
echo 'Payment term:  ', $financials->dueDays, ' days', PHP_EOL;
echo 'Direct debit:  ', $financials->directDebit ? 'yes, ' . $financials->payCode : 'no', PHP_EOL;
echo 'E-invoicing:   ', $financials->eInvoicing ? $financials->eInvoicingEmail ?? 'yes' : 'no', PHP_EOL;
echo 'VAT code:      ', $financials->vatCode ?? '-', PHP_EOL;
echo 'Account:       ', $financials->balanceAccount, PHP_EOL;
echo 'Credit limit:  ', $credit->creditLimit, PHP_EOL;
echo 'Reminders:     ', $credit->sendReminder->name, PHP_EOL;
echo 'VAT number:    ', $customer->vatNumber() ?? '-', PHP_EOL;
echo 'CoC number:    ', $customer->chamberOfCommerceNumber() ?? '-', PHP_EOL;

foreach ($customer->addresses as $address) {
    echo 'Address ', $address->id, ':     ', $address->type->value, $address->default ? ' (default)' : '', ', ',
        implode(', ', array_filter([$address->field2, $address->postcode, $address->city, $address->country])), PHP_EOL;
}

foreach ($customer->banks as $bank) {
    echo 'Bank ', $bank->id, ':        ', $bank->iban ?? $bank->accountNumber ?? '-',
        $bank->default ? ' (default)' : '', PHP_EOL;
}

foreach ($customer->paymentConditions as $condition) {
    echo 'Discount:      ', $condition->discountPercentage, '% within ', $condition->discountDays, ' days', PHP_EOL;
}
