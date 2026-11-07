<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\CashBook;

use SolveBeam\Twinfield\CashBook\CashBook;
use SolveBeam\Twinfield\CashBook\CashBookInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates cash book KASTST once (Twinfield cannot delete cash books), then changes
 * its name and short name and restores them.
 */
final class CashBookResourceTest extends WriteTestCase
{
    public function testCreatesOnceThenChangesAndRestores(): void
    {
        $cashBooks = $this->twinfield->cashBooks();
        $original = $cashBooks->find('KASTST') ?? $this->create();

        try {
            $changed = $cashBooks->update(
                $original->edit()
                    ->name('PHP SDK test (changed)')
                    ->shortName('SDK test 2'),
            );

            self::assertSame('PHP SDK test (changed)', $changed->name);
            self::assertSame('SDK test 2', $changed->shortName);
            self::assertSame($original->generalLedgerAccount, $changed->generalLedgerAccount);
        } finally {
            $restored = $cashBooks->update($original->edit());
        }

        self::assertEquals($original, $restored);
    }

    private function create(): CashBook
    {
        $cashBook = $this->twinfield->cashBooks()->create(
            CashBookInput::create('KASTST', 'PHP SDK test')->shortName('SDK test'),
        );

        self::assertSame('PHP SDK test', $cashBook->name);
        self::assertSame('SDK test', $cashBook->shortName);

        return $cashBook;
    }
}
