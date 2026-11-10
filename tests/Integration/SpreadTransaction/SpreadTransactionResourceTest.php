<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\SpreadTransaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live spread reads (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_PURCHASE_TRANSACTION to an existing purchase transaction, e.g. `INK:202600021`,
 * to also read its spread: there is no search that gives transaction numbers.
 */
final class SpreadTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsForAnUnknownNumber(): void
    {
        $this->expectException(ProcessXmlException::class);

        $this->twinfield->spreadTransactions()->get('INK', 209999999);
    }

    public function testReadsTheSpreadOfAPurchaseTransaction(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_PURCHASE_TRANSACTION');

        if ($setting === null) {
            self::markTestSkipped(
                'Set TWINFIELD_PURCHASE_TRANSACTION, e.g. INK:202600021, to read the spread of a purchase transaction.',
            );
        }

        [$dayBook, $number] = explode(':', $setting) + [1 => ''];

        $spread = $this->twinfield->spreadTransactions()->get($dayBook, (int) $number);

        self::assertSame($dayBook, $spread->original->dayBook);
        self::assertSame((int) $number, $spread->original->number);

        foreach ($spread->spreadTransactions as $transaction) {
            self::assertNotSame($spread->original->number, $transaction->number);
        }

        if ($spread->isSpread()) {
            self::assertNotNull($spread->settings);
        }
    }
}
