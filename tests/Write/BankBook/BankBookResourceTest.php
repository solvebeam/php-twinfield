<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\BankBook;

use SolveBeam\Twinfield\BankBook\BankAccountInput;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\BankBook\BankBookInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates bank book BNKTST once (Twinfield cannot delete bank books), then changes
 * its name, short name and bank account and restores them.
 */
final class BankBookResourceTest extends WriteTestCase
{
    public function testCreatesOnceThenChangesAndRestores(): void
    {
        $bankBooks = $this->twinfield->bankBooks();
        $original = $bankBooks->find('BNKTST') ?? $this->create();

        try {
            $changed = $bankBooks->update(
                $original->edit()
                    ->name('PHP SDK test (changed)')
                    ->shortName('SDK test 2')
                    ->bankAccount($original->bankAccount->edit()->name('Changed B.V.')->bic('ABNANL2A')),
            );

            self::assertSame('PHP SDK test (changed)', $changed->name);
            self::assertSame('SDK test 2', $changed->shortName);
            self::assertSame('Changed B.V.', $changed->bankAccount->name);
            self::assertSame('ABNANL2A', $changed->bankAccount->bic);
            self::assertSame($original->generalLedgerAccount, $changed->generalLedgerAccount);
        } finally {
            $restored = $bankBooks->update($original->edit());
        }

        self::assertEquals($original, $restored);
    }

    private function create(): BankBook
    {
        $bankBook = $this->twinfield->bankBooks()->create(
            BankBookInput::create('BNKTST', 'PHP SDK test')
                ->shortName('SDK test')
                ->bankAccount(
                    BankAccountInput::create()->name('Example B.V.')->iban('NL91ABNA0417164300')->country('NL'),
                ),
        );

        self::assertSame('PHP SDK test', $bankBook->name);
        self::assertSame('NL91ABNA0417164300', $bankBook->bankAccount->iban);

        return $bankBook;
    }
}
