# List PSD2 feeds

Lists the PSD2 bank feeds of all companies with the date their consent expires, and warns when it expires within 30 days (REST API).

- `bankFeeds()->psd2()` gives the feeds of the company; `inAllCompanies()` those of all companies the user has access to.
- `expiresBetween()` filters on the expiry date.

Needs the scope `readBankAccounts`. The test companies have no PSD2 feeds, so this follows the Swagger definition.

## Code

```php
$twinfield = Example::twinfield();

$warnFrom = new DateTimeImmutable('+30 days');

foreach ($twinfield->bankFeeds()->psd2()->inAllCompanies()->all() as $feed) {
    echo sprintf(
        '%s %s %s: consent since %s, expires %s%s',
        $feed->account->companyCode,
        $feed->account->companyName,
        $feed->account->iban,
        $feed->consentOn->format('Y-m-d'),
        $feed->expiresOn->format('Y-m-d'),
        $feed->expiresOn < $warnFrom ? ' (renew soon)' : '',
    ), PHP_EOL;
}
```

## Run

```sh
php examples/bank-feeds/list-psd2-feeds/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
1000 Example Holding B.V. NL91ABNA0417164300: consent since 2026-05-20, expires 2026-11-16 (renew soon)
1001 Example Retail B.V. NL02RABO0123456789: consent since 2026-09-01, expires 2027-02-28
```
