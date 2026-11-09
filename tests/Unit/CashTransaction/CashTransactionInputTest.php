<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CashTransaction;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\CashBook\CashBook;
use SolveBeam\Twinfield\CashTransaction\CashTransactionInput;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class CashTransactionInputTest extends TestCase
{
    public function testForCashBookUsesItsLedgerAccount(): void
    {
        $input = CashTransactionInput::forCashBook($this->cashBook('1000'), new DateTimeImmutable('2026-11-03'));

        self::assertSame('KAS', $input->dayBook());
        self::assertSame('1000', $input->account());
    }

    public function testForCashBookNeedsALedgerAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cash book KAS has no ledger account');

        CashTransactionInput::forCashBook($this->cashBook(null), new DateTimeImmutable('2026-11-03'));
    }

    private function cashBook(?string $account): CashBook
    {
        return new CashBook(company: TestTokens::company('1000'), code: 'KAS', name: 'Kas', shortName: null, generalLedgerAccount: $account);
    }
}
