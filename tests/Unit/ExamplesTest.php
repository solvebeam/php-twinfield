<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the examples consistent, so each one can become a docs page.
 */
final class ExamplesTest extends TestCase
{
    /** Examples that show their own setup instead of Example::twinfield(). */
    private const OWN_SETUP = [
        'getting-started/authentication',
        'getting-started/login',
        'getting-started/token-info',
    ];

    /** Examples that change data: skipped by `composer test:live`, run by `composer test:write`. */
    public const WRITES = [
        'activities/create-activity',
        'activities/delete-activity',
        'activities/update-activity',
        'asset-methods/create-asset-method',
        'asset-methods/delete-asset-method',
        'asset-methods/update-asset-method',
        'bank-books/create-bank-book',
        'bank-books/update-bank-book',
        'bank-transactions/bulk-import-with-errors',
        'bank-transactions/create-bank-transaction',
        'bank-transactions/delete-bank-transaction',
        'bank-transactions/update-bank-transaction',
        'cash-books/create-cash-book',
        'cash-books/update-cash-book',
        'cost-centers/create-cost-center',
        'cost-centers/delete-cost-center',
        'cost-centers/update-cost-center',
        'periods/rename-period',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        $root = dirname(__DIR__, 2) . '/examples/';

        foreach (glob($root . '*/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = substr($directory, strlen($root));

            yield $name => [$name];
        }
    }

    public function testThereAreExamples(): void
    {
        self::assertNotEmpty(iterator_to_array(self::examples()));
    }

    #[DataProvider('examples')]
    public function testExampleIsComplete(string $name): void
    {
        $directory = dirname(__DIR__, 2) . '/examples/' . $name;

        self::assertFileExists($directory . '/example.php');
        self::assertFileExists($directory . '/README.md');

        $code = (string) file_get_contents($directory . '/example.php');
        $readme = (string) file_get_contents($directory . '/README.md');

        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $code);
        self::assertStringContainsString("require __DIR__ . '/../../../vendor/autoload.php';", $code);

        if (!in_array($name, self::OWN_SETUP, true)) {
            self::assertStringContainsString('$twinfield = Example::twinfield();', $code, 'Examples use the one-line setup.');
        }

        self::assertMatchesRegularExpression('~^# \S~', $readme, 'README starts with a title.');
        self::assertStringContainsString('## Code', $readme);
        self::assertStringContainsString("php examples/{$name}/example.php", $readme, 'README shows how to run it.');

        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/examples/README.md');

        self::assertStringContainsString("]({$name}/)", $index, 'Example is listed in examples/README.md.');

        exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($directory . '/example.php')), $output, $exitCode);

        self::assertSame(0, $exitCode, implode("\n", $output));
    }
}
