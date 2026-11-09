<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Transaction;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\TransactionLineReference;

final class TransactionLineReferenceTest extends TestCase
{
    public function testPointsToTheTransaction(): void
    {
        $line = new TransactionLineReference(TestTokens::company(), 'VRK', 202600003, 2);

        self::assertFalse($line->isDraft());
        self::assertSame('VRK', $line->transaction()->dayBook);
        self::assertSame(202600003, $line->transaction()->number);
        self::assertTrue($line->equals(new TransactionLineReference(TestTokens::company(), 'VRK', 202600003, 2)));
        self::assertFalse($line->equals(new TransactionLineReference(TestTokens::company(), 'VRK', 202600003, 1)));
        self::assertFalse($line->equals(new TransactionLineReference(TestTokens::company('1001'), 'VRK', 202600003, 2)));
    }

    public function testADraftHasNoTransactionNumber(): void
    {
        $line = new TransactionLineReference(TestTokens::company(), 'BNK', -1, 1);

        self::assertTrue($line->isDraft());

        $this->expectException(LogicException::class);

        $line->transaction();
    }

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function invalid(): array
    {
        return [
            'empty day book' => ['', 202600003, 1],
            'number 0' => ['VRK', 0, 1],
            'number -2' => ['VRK', -2, 1],
            'line 0' => ['VRK', 202600003, 0],
        ];
    }

    #[DataProvider('invalid')]
    public function testRefusesInvalidValues(string $dayBook, int $number, int $line): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransactionLineReference(TestTokens::company(), $dayBook, $number, $line);
    }
}
