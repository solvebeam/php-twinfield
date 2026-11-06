# Write tests

Tests in this folder create and change data in Twinfield. Extend
`SolveBeam\Twinfield\Tests\Support\WriteTestCase` and use `$this->company()`.

They only run via `composer test:write`, which asks you to type the company
code (`1002` or `1002_3`) as confirmation. Without it they are skipped, and
the write guard blocks any write request.
