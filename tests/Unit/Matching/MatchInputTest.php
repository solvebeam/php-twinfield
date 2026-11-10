<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Matching;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\DimensionType\DimensionTypeCode;
use SolveBeam\Twinfield\Matching\MatchCode;
use SolveBeam\Twinfield\Matching\MatchInput;
use SolveBeam\Twinfield\Matching\WriteOff;
use SolveBeam\Twinfield\Matching\WriteOffType;
use SolveBeam\Twinfield\Transaction\TransactionLineReference;
use SolveBeam\Twinfield\Tests\Support\TestTokens;

/**
 * The three requests of the documentation, with `CASH` for the cash day book as there.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 */
final class MatchInputTest extends TestCase
{
    public function testFullPayment(): void
    {
        $input = MatchInput::customers(new DateTimeImmutable('2021-02-11'))
            ->line('CASH', 202100013, 2)
            ->line('VRK', 202100069, 1);

        self::assertSame(
            '<match><set><office>001</office><matchcode>170</matchcode><matchdate>20210211</matchdate><lines>'
            . '<line><transcode>CASH</transcode><transnumber>202100013</transnumber><transline>2</transline></line>'
            . '<line><transcode>VRK</transcode><transnumber>202100069</transnumber><transline>1</transline></line>'
            . '</lines></set></match>',
            $input->toXml('001'),
        );
    }

    public function testPartPayment(): void
    {
        $input = MatchInput::customers(new DateTimeImmutable('2021-02-11'))
            ->line('CASH', 202100014, 2)
            ->line('VRK', 202100070, 1, matchValue: '119');

        self::assertStringContainsString(
            '<line><transcode>VRK</transcode><transnumber>202100070</transnumber><transline>1</transline>'
            . '<matchvalue>119.00</matchvalue></line>',
            $input->toXml('001'),
        );
    }

    public function testDiscount(): void
    {
        $input = MatchInput::customers(new DateTimeImmutable('2021-02-11'))
            ->line('CASH', 202100015, 2)
            ->line('VRK', 202100071, 1, writeOff: WriteOff::discount('2.00'));

        self::assertStringContainsString(
            '<line><transcode>VRK</transcode><transnumber>202100071</transnumber><transline>1</transline>'
            . '<writeoff type="discount">2.00</writeoff></line>',
            $input->toXml('001'),
        );
    }

    public function testNegativeAmountsOnCreditLines(): void
    {
        $input = MatchInput::suppliers(new DateTimeImmutable('2026-11-10'))
            ->line('INK', 202600010, 1, '-50.00', WriteOff::writeOff('-0.05'))
            ->line('BNK', 202600020, 2);

        self::assertStringContainsString(
            '<matchcode>270</matchcode>',
            $input->toXml('1000'),
        );
        self::assertStringContainsString(
            '<matchvalue>-50.00</matchvalue><writeoff type="writeoff">-0.05</writeoff>',
            $input->toXml('1000'),
        );
    }

    public function testAddsALineByReference(): void
    {
        $reference = new TransactionLineReference(TestTokens::company('1000'), 'VRK', 202600070, 1);

        $input = MatchInput::customers(new DateTimeImmutable('2026-11-10'))
            ->line('KAS', 202600014, 2)
            ->lineReference($reference, '119.00');

        self::assertSame('VRK', $input->lines()[1]->dayBook);
        self::assertSame(202600070, $input->lines()[1]->number);
        self::assertSame(1, $input->lines()[1]->line);
        self::assertSame('119.00', $input->lines()[1]->matchValue?->value);
    }

    public function testIsImmutable(): void
    {
        $empty = MatchInput::customers(new DateTimeImmutable('2026-11-10'));
        $one = $empty->line('KAS', 202600014, 2);

        self::assertSame([], $empty->lines());
        self::assertCount(1, $one->lines());
    }

    public function testNeedsTwoLines(): void
    {
        $this->expectException(LogicException::class);

        MatchInput::customers(new DateTimeImmutable('2026-11-10'))->line('KAS', 202600014, 2)->toXml('1000');
    }

    public function testRefusesTheSameLineTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1 of VRK 202600070 is already in the match set.');

        MatchInput::customers(new DateTimeImmutable('2026-11-10'))
            ->line('VRK', 202600070, 1)
            ->line('VRK', 202600070, 1, '10.00');
    }

    /**
     * @return iterable<string, array{string, int, int, string|null}>
     */
    public static function invalidLines(): iterable
    {
        yield 'empty day book' => ['', 202600070, 1, null];
        yield 'lowercase day book' => ['vrk', 202600070, 1, null];
        yield 'number zero' => ['VRK', 0, 1, null];
        yield 'line zero' => ['VRK', 202600070, 0, null];
        yield 'match value zero' => ['VRK', 202600070, 1, '0.00'];
        yield 'match value with three decimals' => ['VRK', 202600070, 1, '1.005'];
    }

    #[DataProvider('invalidLines')]
    public function testRefusesAnInvalidLine(string $dayBook, int $number, int $line, ?string $matchValue): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var numeric-string|null $matchValue */
        MatchInput::customers(new DateTimeImmutable('2026-11-10'))->line($dayBook, $number, $line, $matchValue);
    }

    public function testRefusesAZeroWriteOff(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WriteOff::discount('0');
    }

    public function testWriteOffTypes(): void
    {
        self::assertSame(WriteOffType::Currency, WriteOff::currency('1.00')->type);
        self::assertSame(WriteOffType::WriteOff, WriteOff::writeOff('1.00')->type);
        self::assertSame(WriteOffType::Discount, WriteOff::discount('1.00')->type);
    }

    public function testDimensionTypeOfAMatchCode(): void
    {
        self::assertSame(DimensionTypeCode::BalanceSheet, MatchCode::SuspenseAccounts->dimensionType());
        self::assertSame(DimensionTypeCode::AccountsReceivable, MatchCode::Customers->dimensionType());
        self::assertSame(DimensionTypeCode::AccountsPayable, MatchCode::Suppliers->dimensionType());
        self::assertSame(DimensionTypeCode::Projects, MatchCode::Projects->dimensionType());
        self::assertSame('070', MatchInput::suspenseAccounts(new DateTimeImmutable())->matchCode->value);
        self::assertSame('370', MatchInput::projects(new DateTimeImmutable())->matchCode->value);
    }
}
