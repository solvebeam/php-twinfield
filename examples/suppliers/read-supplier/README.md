# Read supplier

Reads one supplier, by the code you pass or else the first one, and shows its main settings.

`get()` returns a strict `Supplier`: required values are never null, and optional values are null when they are not set in Twinfield. The settings are grouped like in Twinfield: `financials` (payment term, how the supplier is paid, external supplier number, VAT code, balance sheet account), `remittanceAdvice`, and the lists `addresses`, `banks`, `groups`, `postingRules` and `paymentConditions`.

`financials->meansOfPayment` tells how Twinfield pays the supplier: `PaymentFile` (credit transfer, with the payment type in `payCode`), `Cheques`, or `None` when you pay outside Twinfield.

Posting rules propose how a purchase invoice is booked: per line a general ledger account, cost center and VAT code. A line without a fixed share has `ratio` null. `defaultAddress()`, `defaultBank()`, `vatNumber()` and `chamberOfCommerceNumber()` are shortcuts for what you usually need. A code that does not exist throws a `DimensionNotFoundException`; use `find()` to get null instead.

## Code

```php
$twinfield = Example::twinfield();

// The code from the command line, or else the first supplier.
$code = $argv[1] ?? $twinfield->suppliers()->search()->limit(1)->get()->items[0]->code
    ?? exit('The company has no suppliers.' . PHP_EOL);

try {
    $supplier = $twinfield->suppliers()->get($code);
} catch (DimensionNotFoundException $exception) {
    echo 'Not found: ', $exception->reason, PHP_EOL;

    exit(1);
}

$financials = $supplier->financials;

echo 'Code:          ', $supplier->code, PHP_EOL;
echo 'Name:          ', $supplier->name, PHP_EOL;
echo 'Status:        ', $supplier->status->value, $supplier->inUse ? ' (in use)' : '', PHP_EOL;
echo 'Payment term:  ', $financials->dueDays, ' days', PHP_EOL;
echo 'Paid by:       ', match ($financials->meansOfPayment) {
    MeansOfPayment::PaymentFile => 'credit transfer, ' . $financials->payCode,
    MeansOfPayment::Cheques => 'cheque',
    MeansOfPayment::None => 'not by Twinfield',
}, PHP_EOL;
echo 'Our number:    ', $financials->relationsReference ?? '-', PHP_EOL;
echo 'VAT code:      ', $financials->vatCode ?? '-', PHP_EOL;
echo 'Account:       ', $financials->balanceAccount, PHP_EOL;
echo 'Remittance:    ',
    $supplier->remittanceAdvice?->email ?? $supplier->remittanceAdvice?->sendType->name ?? '-', PHP_EOL;
echo 'VAT number:    ', $supplier->vatNumber() ?? '-', PHP_EOL;
echo 'CoC number:    ', $supplier->chamberOfCommerceNumber() ?? '-', PHP_EOL;

foreach ($supplier->addresses as $address) {
    echo 'Address ', $address->id, ':     ', $address->type->value, $address->default ? ' (default)' : '', ', ',
        implode(', ', array_filter([$address->field2, $address->postcode, $address->city, $address->country])), PHP_EOL;
}

foreach ($supplier->banks as $bank) {
    echo 'Bank ', $bank->id, ':        ', $bank->iban ?? $bank->accountNumber ?? '-',
        $bank->default ? ' (default)' : '', $bank->blocked === true ? ' (blocked)' : '', PHP_EOL;
}

foreach ($supplier->postingRules as $rule) {
    foreach ($rule->lines as $line) {
        echo 'Posting rule:  ', $line->generalLedger, $line->costCenter === null ? '' : ' / ' . $line->costCenter,
            ', VAT ', $line->vatCode ?? '-', $line->ratio === null ? '' : ', ' . $line->ratio . '%', PHP_EOL;
    }
}

foreach ($supplier->paymentConditions as $condition) {
    echo 'Discount:      ', $condition->discountPercentage, '% within ', $condition->discountDays, ' days', PHP_EOL;
}
```

## Run

```sh
php examples/suppliers/read-supplier/example.php
```

```text
Code:          2002
Name:          Example Office Supplies B.V.
Status:        active (in use)
Payment term:  14 days
Paid by:       credit transfer, SEPANLCT
Our number:    CUST-4711
VAT code:      IH
Account:       1600
Remittance:    accounts@example.com
VAT number:    NL001234567B01
CoC number:    -
Address 1:     invoice (default), Oudegracht 1, 3511 AA, Utrecht, NL
Address 2:     postal, Postbus 10, 3500 AA, Utrecht, NL
Bank 1:        NL91ABNA0417164300 (default)
Posting rule:  4500 / 100, VAT IH, 80%
Posting rule:  4500, VAT IH, 20%
Discount:      2.75% within 5 days
```
