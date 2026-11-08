# List projects

Lists the code and name of all projects of the company.

Projects are dimensions of type `PRJ`. In Twinfield Time & Expenses, hours are booked on an activity of a project; in the ledger, projects are dimensions at level 3 (activities at level 4). `projects()->search()` searches them with the [Finder](https://developers.twinfield.com/documentation/api/miscellaneous/finder/); `all()` goes through all pages for you, fetching the next page only when you get there.

Each result is a `ProjectSummary` with `code` and `name`. Use `get()` for all settings of a project (see [Read project](../read-project/)).

## Code

```php
$twinfield = Example::twinfield();

foreach ($twinfield->projects()->search()->all() as $project) {
    echo $project->code, '  ', $project->name, PHP_EOL;
}
```

## Run

```sh
php examples/projects/list-projects/example.php
```

Add `TWINFIELD_DEBUG=1` to see the SOAP request and response.

```text
P0001  Website redesign
P0002  Internal projects & overhead
```
