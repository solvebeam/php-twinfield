# AGENTS.md

## Code style

- Pass multiple arguments to `echo` separated by commas instead of concatenating them with `.`:

  ```php
  echo $office->code, ' ', $office->name, PHP_EOL;
  ```

  Not:

  ```php
  echo $office->code . ' ' . $office->name . PHP_EOL;
  ```
