# Create supplier

> **This example changes data in Twinfield.** It deletes the supplier again, but only run it on a test administration.

Creates a supplier that Twinfield pays by SEPA credit transfer, with an invoice address, a bank account, your customer number at the supplier, a remittance advice by email and a payment discount, and deletes it again.

`SupplierInput` is immutable and checks every value before anything is sent: lengths, codes and IBANs, which are normalised (`NL91 ABNA 0417 1643 00` becomes `NL91ABNA0417164300`) and must pass the checksum. Only what you set is sent; everything else gets the default of the supplier dimension type (`CRD`), such as the balance sheet account.

How the supplier is paid is set in one call, so the settings cannot contradict each other: `creditTransfer($payCode)`, `cheques()` or `withoutPayments()`.

Without `code()`, Twinfield gives the supplier the next free code. With a code, `create()` reads first and throws a `DimensionAlreadyExistsException` when the code is taken, so an existing supplier is never overwritten. Twinfield refusals, such as an unknown payment type, are thrown as a `ProcessXmlException` with the message per element.

## Code

```php
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
```

## Run

```sh
php examples/suppliers/create-supplier/example.php
```

```text
Created 2010 Example Office Supplies B.V.
Paid by: PaymentFile (SEPANLCT)
IBAN:    NL91ABNA0417164300
Deleted 2010
```
