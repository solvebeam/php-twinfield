<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Customer\CustomerInput;
use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Examples\Example;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$customers = $twinfield->customers();

$customer = $customers->create(
    CustomerInput::create('Example Trading B.V.')
        ->dueDays(30)
        ->address(
            AddressInput::invoice('Example Trading B.V.')
                ->field(2, 'Damrak 1')
                ->city('Amsterdam')
                ->country('NL')
                ->default(),
        ),
);

try {
    echo 'Before: due in ', $customer->financials->dueDays, ' days, addresses: ', count($customer->addresses),
        ', bank accounts: ', count($customer->banks), PHP_EOL;

    // edit() keeps every current value; change the invoice address by its id and add the rest.
    $invoice = $customer->defaultAddress()?->edit()->field(2, 'Coolsingel 40')->city('Rotterdam');

    $customer = $customers->update(
        $customer->edit()
            ->dueDays(14)
            ->address($invoice ?? throw new LogicException('No invoice address.'))
            ->address(
                AddressInput::postal('Example Trading B.V.')->field(2, 'Postbus 123')->city('Rotterdam')->country('NL'),
            )
            ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example Trading B.V.')->default()),
    );

    echo 'After:  due in ', $customer->financials->dueDays, ' days, addresses: ', count($customer->addresses),
        ', bank accounts: ', count($customer->banks), PHP_EOL;

    foreach ($customer->addresses as $address) {
        echo '  ', $address->type->value, ': ', $address->field2, ', ', $address->city, PHP_EOL;
    }
} finally {
    $customers->delete($customer->code);
}
