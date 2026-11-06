<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\BankBook;

use SolveBeam\Twinfield\BankBook\BankBookNotFoundException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live BankBookService queries and Finder searches (read-only) against TWINFIELD_COMPANY.
 */
final class BankBookResourceTest extends IntegrationTestCase
{
    public function testSearchesAndReadsBankBooks(): void
    {
        $bankBooks = $this->twinfield->bankBooks();
        $summaries = iterator_to_array($bankBooks->search()->all(), false);

        if ($summaries === []) {
            self::markTestSkipped('The company has no bank books.');
        }

        foreach ($summaries as $summary) {
            $bankBook = $bankBooks->get($summary->code);

            self::assertSame($summary->code, $bankBook->code);
            self::assertSame($summary->name, $bankBook->name);
        }
    }

    public function testUpdateWithoutChangesSendsNoCommand(): void
    {
        $bankBooks = $this->twinfield->bankBooks();
        $first = $bankBooks->search()->limit(1)->get()->items[0] ?? null;

        if ($first === null) {
            self::markTestSkipped('The company has no bank books.');
        }

        // The write guard blocks commands, so this fails when update() would send one.
        $bankBook = $bankBooks->get($first->code);

        self::assertEquals($bankBook, $bankBooks->update($bankBook->edit()));
    }

    public function testThrowsNotFound(): void
    {
        $this->expectException(BankBookNotFoundException::class);

        $this->twinfield->bankBooks()->get('NOPE_NOT_FOUND');
    }
}
