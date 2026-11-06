# List activities

Lists the code and name of all activities of the company.

Activities are dimensions of type `ACT`: in Twinfield Time & Expenses, hours are booked on an activity of a project. `activities()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/); `all()` goes through all pages for you, fetching the next page only when you get there.

Each result is an `ActivitySummary` with `code` and `name`. Use `get()` for all settings of an activity (see [Read activity](../read-activity/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->activities()->search()->all() as $activity) {
    echo $activity->code, '  ', $activity->name, PHP_EOL;
}
```

## Run

```sh
php examples/activities/list-activities/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
A001  Financial administration
A002  VAT return
```
