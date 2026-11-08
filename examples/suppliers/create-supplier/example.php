<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Dimension\AddressInput;
use SolveBeam\Twinfield\Dimension\BankInput;
use SolveBeam\Twinfield\Dimension\RemittanceAdviceSendType;
use SolveBeam\Twinfield\Examples\Example;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Supplier\SupplierInput;

require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();

$suppliers = $twinfield->suppliers();

$input = SupplierInput::create('Example Office Supplies B.V.')
    ->website('www.example.com')
    ->dueDays(14)
    ->relationsReference('CUST-4711')
    ->creditTransfer('SEPANLCT')
    ->remittanceAdvice(RemittanceAdviceSendType::ByEmail, 'accounts@example.com')
    ->address(
        AddressInput::invoice('Example Office Supplies B.V.')
            ->field(2, 'Oudegracht 1')
            ->postcode('3511 AA')
            ->city('Utrecht')
            ->country('NL')
            ->default(),
    )
    ->bank(BankInput::create()->iban('NL91 ABNA 0417 1643 00')->ascription('Example Office Supplies B.V.')->default())
    ->paymentCondition(5, '2');

try {
    // Without code(), Twinfield gives the supplier the next free code.
    $supplier = $suppliers->create($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

echo 'Created ', $supplier->code, ' ', $supplier->name, PHP_EOL;
echo 'Paid by: ', $supplier->financials->meansOfPayment->name, ' (', $supplier->financials->payCode, ')', PHP_EOL;
echo 'IBAN:    ', $supplier->defaultBank()?->iban, PHP_EOL;

// Clean up: delete the example supplier again.
$suppliers->delete($supplier->code);

echo 'Deleted ', $supplier->code, PHP_EOL;
