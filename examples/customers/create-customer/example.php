<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Customer\SendReminder;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customers = $twinfield->customers();

$input = CustomerInput::create('Example Trading B.V.')
    ->website('www.example.com')
    ->dueDays(30)
    ->eInvoicing('invoices@example.com')
    ->sendReminder(SendReminder::ByEmail, 'finance@example.com')
    ->address(
        AddressInput::invoice('Example Trading B.V.')
            ->field(1, 'T.a.v. J. Jansen')
            ->field(2, 'Damrak 1')
            ->postcode('1012 AB')
            ->city('Amsterdam')
            ->country('NL')
            ->default(),
    )
    ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->ascription('Example Trading B.V.')->default())
    ->paymentCondition(8, '2');

try {
    // Without code(), Twinfield gives the customer the next free code.
    $customer = $customers->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $customer->code, ' ', $customer->name, PHP_EOL;
echo 'Address: ', $customer->defaultAddress()?->field2, ', ', $customer->defaultAddress()?->city, PHP_EOL;
echo 'IBAN:    ', $customer->defaultBank()?->iban, PHP_EOL;

// Clean up: delete the example customer again.
$customers->delete($customer->code);

echo 'Deleted ', $customer->code, PHP_EOL;
