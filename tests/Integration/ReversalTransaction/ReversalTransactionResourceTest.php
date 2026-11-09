<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\ReversalTransaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live reversal reads (read-only) against TWINFIELD_COMPANY.
 *
 * Set TWINFIELD_JOURNAL_TRANSACTION to an existing journal transaction, e.g. `MEMO:202600003`,
 * to also read its reversal: there is no search that gives transaction numbers.
 */
final class ReversalTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsForAnUnknownNumber(): void
    {
        $this->expectException(ProcessXmlException::class);

        $this->twinfield->reversalTransactions()->get('MEMO', 209999999);
    }

    public function testReadsTheReversalOfAJournalTransaction(): void
    {
        $setting = $this->environment->config->get('TWINFIELD_JOURNAL_TRANSACTION');

        if ($setting === null) {
            self::markTestSkipped(
                'Set TWINFIELD_JOURNAL_TRANSACTION, e.g. MEMO:202600003, to read the reversal of a journal.',
            );
        }

        [$dayBook, $number] = explode(':', $setting) + [1 => ''];

        $reversal = $this->twinfield->reversalTransactions()->get($dayBook, (int) $number);

        self::assertSame($dayBook, $reversal->original->dayBook);
        self::assertSame((int) $number, $reversal->original->number);

        if ($reversal->reversed !== null) {
            self::assertNotSame($reversal->original->dayBook, $reversal->reversed->dayBook);
        }
    }
}
