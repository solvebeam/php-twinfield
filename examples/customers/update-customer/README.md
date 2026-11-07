# Update customer

> **This example changes data in Twinfield.** It creates a customer, changes it and deletes it again. Only run it on a test administration.

`$customer->edit()` gives a `CustomerInput` with every current value, including all addresses and bank accounts with their ids. Change what you need and pass it to `update()`, which checks that the customer exists and returns the saved `Customer`.

- `address()` and `bank()` with the id of an existing entry (from `$address->edit()`) change that entry; without id they add one.
- A new default address or bank account makes the others non-default: a customer has at most one.
- Addresses and bank accounts are sent as a whole, so `withoutAddress($id)` and `withoutBank($id)` remove one. `withoutPostingRule($id)` deletes a posting rule.
- Setting a value to null clears it, e.g. `website(null)`.

## Code

```php
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
```

## Run

```sh
php examples/customers/update-customer/example.php
```

```text
Before: due in 30 days, addresses: 1, bank accounts: 0
After:  due in 14 days, addresses: 2, bank accounts: 1
  invoice: Coolsingel 40, Rotterdam
  postal: Postbus 123, Rotterdam
```
