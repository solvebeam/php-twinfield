# Update supplier

> **This example changes data in Twinfield.** It creates a supplier, changes it and deletes it again. Only run it on a test administration.

`$supplier->edit()` gives a `SupplierInput` with every current value, including all addresses and bank accounts with their ids. Change what you need and pass it to `update()`, which checks that the supplier exists and returns the saved `Supplier`.

- `address()` and `bank()` with the id of an existing entry (from `$bank->edit()`) change that entry; without id they add one.
- A new default address or bank account makes the others non-default: a supplier has at most one.
- Addresses and bank accounts are sent as a whole, so `withoutAddress($id)` and `withoutBank($id)` remove one. `withoutPostingRule($id)` deletes a posting rule.
- Setting a value to null clears it, e.g. `relationsReference(null)`.

## Code

```php
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
```

## Run

```sh
php examples/suppliers/update-supplier/example.php
```

```text
Before: due in 30 days, paid by PaymentFile, bank accounts: 1
After:  due in 14 days, paid by None, bank accounts: 2
  NL91ABNA0417164300 ABNANL2A (default)
  BE68539007547034
```
