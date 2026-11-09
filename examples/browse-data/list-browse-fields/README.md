# List browse fields

Shows the browse fields with their data type, the Finder type to look up values, and the values of fields with a fixed list. Use the values in a criterion, e.g. `equal('fin.trs.head.status', 'final')`.

- `browseDefinitions()->fields()`: all browse fields (about 260), as `BrowseField` objects with `code`, `dataType`, `finder` and `options`. Which fields a browse code has is in its [definition](../read-browse-definition/).
- `dataType` is a `BrowseFieldDataType`: `string`, `special` (a fixed list of values), `value` (an amount), `long`, `decimal`, `date` or `datetime`.
- `finder` is the Finder type Twinfield uses to look up values, e.g. `TRS` for day books and `DIM` for dimensions: search them with `$twinfield->finder()`.
- `options` are the values of a `special` field, as `BrowseFieldOption` with the `value` to use in a criterion and the `name` in the language of the user. Note `normal` of `fin.trs.head.status`: provisional and final together.

The browse fields are also in [`browse-definitions/browsefields.xml`](../../../browse-definitions/browsefields.xml) in the repository. Unlike other ProcessXml responses, `<browsefields>` has no `result` attribute; the SDK handles that.

## Code

```php
$twinfield = Example::twinfield();

$prefix = $argv[1] ?? 'fin.trs.head.';

$fields = array_values(array_filter(
    $twinfield->browseDefinitions()->fields(),
    static fn ($field): bool => str_starts_with($field->code, $prefix),
));

echo count($fields), ' browse field(s) starting with ', $prefix, PHP_EOL;

foreach ($fields as $index => $field) {
    $options = array_map(
        static fn (BrowseFieldOption $option): string => $option->name === null
            ? $option->value
            : sprintf('%s (%s)', $option->value, $option->name),
        $field->options,
    );

    echo sprintf(
        '[%d/%d] %s: %s%s%s',
        $index + 1,
        count($fields),
        $field->code,
        $field->dataType->value,
        $field->finder === null ? '' : ', Finder ' . $field->finder,
        $options === [] ? '' : ', values ' . implode(', ', $options),
    ), PHP_EOL;
}
```

## Run

```sh
php examples/browse-data/list-browse-fields/example.php                   # fields starting with fin.trs.head.
php examples/browse-data/list-browse-fields/example.php fin.trs.line.match   # the match fields of a line
```

Add `TWINFIELD_DEBUG=1` to see the SOAP requests and responses.

```text
33 browse field(s) starting with fin.trs.head.
[1/33] fin.trs.head.bankcode: special, values BNK, BNK2, KAS
[2/33] fin.trs.head.banktype: special, values bank (Bank), cashbook (Kas)
[5/33] fin.trs.head.code: string, Finder TRS
[6/33] fin.trs.head.curcode: string, Finder CUR
…
[9/33] fin.trs.head.regime: special, values Generic (Generiek), Commercial (Commercieel), Economic (Economisch), Fiscal (Fiscaal)
…
[13/33] fin.trs.head.date: date
[14/33] fin.trs.head.inpdate: datetime
[15/33] fin.trs.head.modified: datetime
[16/33] fin.trs.head.name: string
…
[26/33] fin.trs.head.status: special, values draft (Klad), temporary (Concept), final (Definitief), inuse (In gebruik), normal (Concept & definitief)
…
[31/33] fin.trs.head.yearperiod: string
```
