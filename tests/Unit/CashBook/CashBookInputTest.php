<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CashBook;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\CashBook\CashBook;
use SolveBeam\Twinfield\CashBook\CashBookInput;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

final class CashBookInputTest extends TestCase
{
    public function testIsImmutable(): void
    {
        $input = CashBookInput::create('KAS', 'Kas');
        $changed = $input->name('Kas winkel')->shortName('Winkel')->generalLedgerAccount('1010');

        self::assertSame('Kas', $input->name);
        self::assertNull($input->shortName);
        self::assertNull($input->generalLedgerAccount);
        self::assertSame('Kas winkel', $changed->name);
        self::assertSame('Winkel', $changed->shortName);
        self::assertSame('1010', $changed->generalLedgerAccount);
    }

    public function testEmptyStringsRemoveValues(): void
    {
        $input = CashBookInput::create('KAS', 'Kas')->shortName('Kas')->shortName('')->generalLedgerAccount('');

        self::assertNull($input->shortName);
        self::assertNull($input->generalLedgerAccount);
    }

    public function testRequiresAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CashBookInput::create('KAS', '');
    }

    public function testRequiresAValidCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CashBookInput::create('', 'Kas');
    }

    public function testEditKeepsAllValues(): void
    {
        $input = (new CashBook(TestTokens::company('1000'), 'KAS', 'Kas', 'Winkel', '1000'))->edit();

        self::assertEquals(CashBookInput::create('KAS', 'Kas')->shortName('Winkel')->generalLedgerAccount('1000'), $input);
    }

    public function testReadModelRequiresAName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cash book name must not be empty.');

        new CashBook(TestTokens::company('1000'), 'KAS', '', null, null);
    }
}
