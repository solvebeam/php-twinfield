# Create customer

> **This example changes data in Twinfield.** It deletes the customer again, but only run it on a test administration.

Creates a customer with an invoice address, a bank account, e-invoicing, reminders by email and a payment discount, and deletes it again.

`CustomerInput` is immutable and checks every value before anything is sent: lengths, codes, amounts (`creditLimit('5000.00')`), and IBANs, which are normalised (`NL91 ABNA 0417 1643 00` becomes `NL91ABNA0417164300`) and must pass the checksum. Only what you set is sent; everything else gets the default of the customer dimension type (`DEB`), such as the balance sheet account.

Without `code()`, Twinfield gives the customer the next free code. With a code, `create()` reads first and throws a `DimensionAlreadyExistsException` when the code is taken, so an existing customer is never overwritten. Twinfield refusals, such as an invalid VAT number, are thrown as a `ProcessXmlException` with the message per element.

## Code

```php
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
```

## Run

```sh
php examples/customers/create-customer/example.php
```

```text
Created 1006 Example Trading B.V.
Address: Damrak 1, Amsterdam
IBAN:    NL91ABNA0417164300
Deleted 1006
```
