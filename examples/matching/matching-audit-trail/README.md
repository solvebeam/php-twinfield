# Matching audit trail

Lists the matches and undone matches of the last 30 days (or since a date), from the REST API.

- `matching()->auditTrailSince($since)` goes through the whole trail, oldest first; `auditTrail($since, $limit)` reads one page of at most 200 events.
- Each event has the match number, the dimension, the user and the matched transaction lines with the matched amounts. `Unmatched` events have no match date.
- The match number is the one `matching()->undo()` needs.

Needs the scope `readBookkeepingMatches`.

## Code

```php
$twinfield = Example::twinfield();

$since = new DateTimeImmutable($argv[1] ?? '-30 days');

foreach ($twinfield->matching()->auditTrailSince($since) as $event) {
    echo sprintf(
        '%s %s match %d on %s by %s',
        $event->logTimestamp->format('Y-m-d H:i'),
        $event->eventType->value,
        $event->matchNumber,
        $event->dimensionCode,
        $event->userCode,
    ), PHP_EOL;

    foreach ($event->lines as $line) {
        echo '  ', $line->dayBook, ' ', $line->number, ' line ', $line->lineId, ': ', $line->matchedAmount, PHP_EOL;
    }
}
```

## Run

```sh
php examples/matching/matching-audit-trail/example.php
php examples/matching/matching-audit-trail/example.php 2026-11-01
```

Add `TWINFIELD_DEBUG=1` to see the requests and responses.

```text
2026-11-04 10:48 Matched match 119 on 1000 by JDOE
  VRK 202600070 line 1: 121.00
  BNK 202600014 line 2: -121.00
2026-11-04 10:52 Unmatched match 119 on 1000 by JDOE
  BNK 202600014 line 2: -121.00
```
