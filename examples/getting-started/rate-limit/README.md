# Rate limit

Reads the [rate limit](/guides/rate-limits/) headers of Twinfield after every request with `$twinfield->lastRateLimit()`: what the request cost and how many API credits are left in the current minute.

- `lastRateLimit()` returns a `RateLimit` with the `X-RateLimit-…` headers of the most recent API response, or `null` before the first request. Here it is never `null`: `Example::twinfield()` has already looked up the company.
- `credited` is what the last request cost: 1 API credit for a query request, 3 for any other request. It shows how Twinfield counted a request: reading a dimension type costs 3 credits, because that request has the root element `<dimensiontype>` instead of `<read>`.
- `remaining` and `limit` are the lowest of all buckets (IP address, client ID, organisation, client ID and organisation). `clientIdRemaining` and `clientIdLimit` are those of the client ID alone.
- Each property is `null` when Twinfield did not send the header, so the example prints `?` for it.
- The value is shared by all clients from one `Twinfield::create()`, for the SOAP web services and the REST API alike.
- `remaining` is not exact, Twinfield warns, and other processes with the same client ID or organisation use the same buckets: it can drop by more than `credited` between two requests.

The REST request needs the scope `readFixedAssets`. See [Scopes](/guides/rest-api/#scopes).

To wait for `Retry-After` and send a request again after a 429, wrap the HTTP client in a `RateLimitRetryClient`: see [Retrying automatically](/guides/rate-limits/#retrying-automatically).

## Code

```php
$twinfield = Example::twinfield();

/**
 * Prints what the last request cost and how many API credits are left in the current minute.
 */
$show = static function (string $request, Twinfield $twinfield): void {
    $rateLimit = $twinfield->lastRateLimit();

    if ($rateLimit === null) {
        echo $request, ': no rate limit headers', PHP_EOL;

        return;
    }

    echo $request, ': cost ', $rateLimit->credited ?? '?',
        ', left ', $rateLimit->remaining ?? '?', ' of ', $rateLimit->limit ?? '?',
        ', for the client ID ', $rateLimit->clientIdRemaining ?? '?', ' of ', $rateLimit->clientIdLimit ?? '?',
        PHP_EOL;
};

// The Finder: SOAP action Search.
$twinfield->finder(FinderType::Countries)->limit(1)->get();
$show('Finder', $twinfield);

// An XML web service: ProcessXml. This read has the root element <dimensiontype>, not <read>,
// so Twinfield does not count it as a query request.
$twinfield->dimensionTypes()->get(DimensionTypeCode::AccountsReceivable);
$show('ProcessXml', $twinfield);

// A Command & Query service: SOAP action Query.
$twinfield->periods()->years();
$show('Command & Query', $twinfield);

// The REST API: HTTP GET.
$twinfield->rest(
    'GET',
    '/organisations/{organisationId}/companies/{companyId}/fixedassets/classes',
    ['limit' => 1],
);
$show('REST GET', $twinfield);

// Slow down before the limit is reached, instead of after a 429.
$rateLimit = $twinfield->lastRateLimit();

if ($rateLimit?->remaining !== null && $rateLimit->remaining < 10) {
    echo 'Almost out of API credits: wait for the next minute.', PHP_EOL;
}
```

## Run

```sh
php examples/getting-started/rate-limit/example.php
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
Finder: cost 1, left 85 of 500, for the client ID 585 of 1000
ProcessXml: cost 3, left 85 of 500, for the client ID 585 of 1000
Command & Query: cost 1, left 82 of 500, for the client ID 582 of 1000
REST GET: cost 1, left 81 of 500, for the client ID 581 of 1000
```
