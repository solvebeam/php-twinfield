<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\Supplier\SupplierInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$suppliers = $twinfield->suppliers();

$supplier = $suppliers->create(
    SupplierInput::create('Example Office Supplies B.V.')
        ->dueDays(30)
        ->creditTransfer('SEPANLCT')
        ->bank(BankInput::create()->iban('NL91ABNA0417164300')->ascription('Example Office Supplies B.V.')->default()),
);

try {
    echo 'Before: due in ', $supplier->financials->dueDays, ' days, paid by ',
        $supplier->financials->meansOfPayment->name, ', bank accounts: ', count($supplier->banks), PHP_EOL;

    // edit() keeps every current value; the bank account is changed by its id, the second one is added.
    $bank = $supplier->defaultBank()?->edit()->bic('ABNANL2A');

    $supplier = $suppliers->update(
        $supplier->edit()
            ->dueDays(14)
            ->withoutPayments()
            ->vatCode('IH')
            ->bank($bank ?? throw new LogicException('No bank account.'))
            ->bank(BankInput::create()->iban('BE68 5390 0754 7034')->ascription('Example Office Supplies NV')),
    );

    echo 'After:  due in ', $supplier->financials->dueDays, ' days, paid by ',
        $supplier->financials->meansOfPayment->name, ', bank accounts: ', count($supplier->banks), PHP_EOL;

    foreach ($supplier->banks as $bank) {
        echo '  ', $bank->iban, ' ', $bank->bic ?? '', $bank->default ? ' (default)' : '', PHP_EOL;
    }
} finally {
    $suppliers->delete($supplier->code);
}
