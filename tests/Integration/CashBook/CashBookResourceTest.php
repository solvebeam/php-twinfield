<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\CashBook;

use SolveBeam\Twinfield\CashBook\CashBookNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live CashBookService queries and Finder searches (read-only) against TWINFIELD_COMPANY.
 */
final class CashBookResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsCashBooks(): void
    {
        $cashBooks = $this->twinfield->cashBooks();
        $summaries = iterator_to_array($cashBooks->search()->all(), false);

        if ($summaries === []) {
            self::markTestSkipped('The company has no cash books.');
        }

        foreach ($summaries as $summary) {
            $cashBook = $cashBooks->get($summary->code);

            self::assertSame($summary->code, $cashBook->code);
            self::assertSame($summary->name, $cashBook->name);
        }
    }

    public function testUpdateWithoutChangesSendsNoCommand(): void
    {
        $cashBooks = $this->twinfield->cashBooks();
        $first = $cashBooks->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no cash books.');
        }

        // The write guard blocks commands, so this fails when update() would send one.
        $cashBook = $cashBooks->get($first->code);

        self::assertEquals($cashBook, $cashBooks->update($cashBook->edit()));
    }

    public function testBankBooksAreNotCashBooks(): void
    {
        $bankBook = $this->twinfield->bankBooks()->search()->limit(1)->get()->items[0] ?? null;

        if ($bankBook === null) {
            self::markTestSkipped('The company has no bank books.');
        }

        self::assertNull($this->twinfield->cashBooks()->find($bankBook->code));
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(CashBookNotFoundException::class);

        $this->twinfield->cashBooks()->get('NOPE_NOT_FOUND');
    }
}
