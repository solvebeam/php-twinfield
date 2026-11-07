# Read customer

Reads one customer, by the code you pass or else the first one, and shows its main settings.

`get()` returns a strict `Customer`: required values are never null, and optional values are null when they are not set in Twinfield. The settings are grouped like in Twinfield: `financials` (payment term, direct debit, e-invoicing, VAT code, balance sheet account), `creditManagement` (credit limit, reminders), `remittanceAdvice`, and the lists `addresses`, `banks`, `groups`, `postingRules` and `paymentConditions`.

`defaultAddress()`, `defaultBank()`, `vatNumber()` and `chamberOfCommerceNumber()` are shortcuts for what you usually need. A code that does not exist throws a `DimensionNotFoundException`; use `find()` to get null instead.

## Code

```php
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
```

## Run

```sh
php examples/customers/read-customer/example.php
```

```text
Code:          1000
Name:          Example Trading B.V.
Status:        active (in use)
Website:       www.example.com
Payment term:  30 days
Direct debit:  yes, SEPANLDD
E-invoicing:   invoices@example.com
VAT code:      VH
Account:       1300
Credit limit:  5000.00
Reminders:     ByEmail
VAT number:    NL001234567B01
CoC number:    12345678
Address 1:     invoice (default), Damrak 1, 1012 AB, Amsterdam, NL
Address 2:     postal, Postbus 123, 1000 AA, Amsterdam, NL
Bank 1:        NL91ABNA0417164300 (default)
Discount:      2% within 8 days
```
