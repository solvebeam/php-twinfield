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
        'articles/create-article',
        'articles/delete-article',
        'articles/update-article-price',
        'classic-asset-methods/create-asset-method',
        'classic-asset-methods/delete-asset-method',
        'classic-asset-methods/update-asset-method',
        'bank-books/create-bank-book',
        'bank-books/update-bank-book',
        'bank-transactions/bulk-import-with-errors',
        'bank-transactions/create-bank-transaction',
        'bank-transactions/delete-bank-transaction',
        'bank-transactions/update-bank-transaction',
        'budgets/create-budget',
        'cash-books/create-cash-book',
        'cash-books/update-cash-book',
        'cost-centers/create-cost-center',
        'cost-centers/delete-cost-center',
        'cost-centers/update-cost-center',
        // Reading a currency sends a <currency> request, which the write guard counts as a write.
        'currencies/create-currency',
        'currencies/delete-currency',
        'currencies/read-currency',
        'currencies/update-currency-rates',
        'customers/create-customer',
        'customers/delete-customer',
        'customers/update-customer',
        // Reading a dimension group sends a <dimensiongroup> request, which the write guard counts as a write.
        'dimension-groups/create-dimension-group',
        'dimension-groups/delete-dimension-group',
        'dimension-groups/read-dimension-group',
        'dimension-groups/update-dimension-group',
        'dimension-types/update-dimension-type',
        'classic-fixed-assets/create-fixed-asset',
        'classic-fixed-assets/delete-fixed-asset',
        'classic-fixed-assets/update-fixed-asset',
        'general-ledger-accounts/create-general-ledger-account',
        'general-ledger-accounts/delete-general-ledger-account',
        'general-ledger-accounts/update-general-ledger-account',
        // Save and delete change a reporting structure of the whole organisation, not only of the company.
        'hierarchies/bind-accounts',
        'hierarchies/delete-hierarchy',
        'hierarchies/save-hierarchy',
        'matching/match-transactions',
        'matching/undo-match',
        'pay-and-collect/create-pay-run',
        'pay-and-collect/finish-pay-run',
        // Posts to the in-transfer ledger: can't be undone.
        'pay-and-collect/pay-run-lifecycle',
        'periods/rename-period',
        'projects/create-project',
        'projects/delete-project',
        'projects/update-project',
        'rates/create-rate',
        'rates/delete-rate',
        'rates/update-rate-price',
        'sales-articles/create-sales-article',
        'suppliers/create-supplier',
        'suppliers/delete-supplier',
        'reversal-transactions/reverse-journal-transaction',
        'spread-transactions/spread-purchase-transaction',
        'time-entries/create-time-entry',
        'suppliers/update-supplier',
        'transaction-blocked-values/register-blocked-value',
        'transaction-blocked-values/unregister-blocked-value',
        'users/create-user',
        'users/delete-user',
        'users/update-user',
        'vat-codes/create-vat-code',
        'vat-codes/delete-vat-code',
        'vat-codes/update-vat-percentage',
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
            self::assertStringContainsString(
                '$twinfield = Example::twinfield();',
                $code,
                'Examples use the one-line setup.',
            );
        }

        self::assertMatchesRegularExpression('~^# \S~', $readme, 'README starts with a title.');
        self::assertStringContainsString('## Code', $readme);
        self::assertStringContainsString("php examples/{$name}/example.php", $readme, 'README shows how to run it.');

        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/examples/README.md');

        self::assertStringContainsString("]({$name}/)", $index, 'Example is listed in examples/README.md.');

        exec(
            sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($directory . '/example.php')),
            $output,
            $exitCode,
        );

        self::assertSame(0, $exitCode, implode("\n", $output));
    }
}
