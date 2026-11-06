<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write;

use PHPUnit\Framework\Attributes\DataProvider;
use SolveBeam\Twinfield\Tests\Support\ExampleRunner;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Tests\Unit\ExamplesTest as ExamplesStructureTest;

/**
 * Runs the examples that change data, on the confirmed write company.
 */
final class ExamplesTest extends WriteTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        foreach (ExamplesStructureTest::WRITES as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('examples')]
    public function testExampleRuns(string $name): void
    {
        usleep(500_000);

        // TWINFIELD_COMPANY and TWINFIELD_ALLOW_WRITES are set by `composer test:write`.
        [$exitCode, $stdout, $stderr] = ExampleRunner::run($name, array_filter(getenv(), 'is_string'));

        self::assertSame(0, $exitCode, "Example {$name} failed:\n{$stderr}");
        self::assertNotSame('', trim($stdout), "Example {$name} printed nothing.");
    }
}
