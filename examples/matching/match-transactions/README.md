# Match transactions

Matches existing transaction lines of a customer with each other (afletteren), e.g. a sales invoice with the cash payment of it.

> **This example changes data in Twinfield.** Only run it on a test administration. Undo a match with the [Undo match](../undo-match/) example.

Pass the lines as `<day book>:<number>:<line>`, with an optional match value for a partial payment, e.g. `VRK:202600070:1:119.00`. Without a match value, Twinfield matches the open amount of the line. `MatchInput::customers()` uses match code 170; use `suppliers()`, `projects()` or `suspenseAccounts()` for the other match codes.

A difference can be written off, given as a discount, or booked as an exchange rate difference with `WriteOff`:

```php
MatchInput::customers($date)
    ->line('KAS', 202600015, 2)
    ->line('VRK', 202600071, 1, writeOff: WriteOff::discount('2.00'));
```

Use a negative amount on a credit line. Twinfield refusals are thrown as `ProcessXmlException`.

## Code

```php
$twinfield = Example::twinfield();

// E.g. `KAS:202600014:2 VRK:202600070:1:119.00`: day book, number, line and an optional match value.
$lines = array_slice($argv ?? [], 1);

if (count($lines) < 2) {
    echo 'Usage: php example.php <day book>:<number>:<line>[:<match value>] …', PHP_EOL;

    exit(0);
}

$input = MatchInput::customers(new DateTimeImmutable('today'));

foreach ($lines as $line) {
    [$dayBook, $number, $lineNumber, $matchValue] = array_pad(explode(':', $line), 4, null);

    /** @var numeric-string|null $matchValue */
    $input = $input->line((string) $dayBook, (int) $number, (int) $lineNumber, $matchValue);
}

try {
    $twinfield->matching()->match($input);
} catch (ProcessXmlException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;

    exit(1);
}

foreach ($input->lines() as $line) {
    $amount = $line->matchValue === null ? 'open amount' : (string) $line->matchValue;

    echo 'Matched ', $line->dayBook, ' ', $line->number, ' line ', $line->line, ': ', $amount, PHP_EOL;
}
```

## Run

The write guard of the examples blocks changes unless you allow them for a company in `TWINFIELD_WRITE_COMPANIES`:

```sh
TWINFIELD_COMPANY=1002_3 TWINFIELD_ALLOW_WRITES=1002_3 php examples/matching/match-transactions/example.php KAS:202600014:2 VRK:202600070:1:119.00
```

The output below follows the documentation; it was not run live yet.

```text
Matched KAS 202600014 line 2: open amount
Matched VRK 202600070 line 1: 119.00
```
