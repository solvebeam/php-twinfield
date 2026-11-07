<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\BankTransaction;

use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;

/**
 * Live transaction reads (read-only) against TWINFIELD_COMPANY.
 */
final class BankTransactionResourceTest extends IntegrationTestCase
{
    public function testThrowsNotFoundForAnUnknownNumber(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield->bankTransactions()->get('BNK', 209999999);
    }

    public function testThrowsNotFoundForAnUnknownDayBook(): void
    {
        $this->expectException(TransactionNotFoundException::class);

        $this->twinfield->cashTransactions()->get('NOPE', 1);
    }
}
