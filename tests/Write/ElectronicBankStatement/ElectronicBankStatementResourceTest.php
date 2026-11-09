<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\ElectronicBankStatement;

use DateTimeImmutable;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementInput;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;
use SolveBeam\Twinfield\Transaction\DebitCredit;

/**
 * Imports one electronic bank statement of 0.01 in and 0.01 out in the first bank book with a
 * ledger account, then imports it again without allowing duplicates.
 *
 * A statement can't be changed or deleted through the API: this leaves the statement (balance
 * 0.00) in the company. Remove it in Twinfield when needed.
 */
final class ElectronicBankStatementResourceTest extends WriteTestCase
{
    public function testImportsAStatement(): void
    {
        $bankBook = $this->bankBook();
        $input = ElectronicBankStatementInput::forBankBook($bankBook->code)
            ->date(new DateTimeImmutable('today'))
            ->receive('0.01', 'PHP SDK write test', reference: 'php-sdk-write-test')
            ->pay('0.01', 'PHP SDK write test', reference: 'php-sdk-write-test');

        $statement = $this->twinfield->electronicBankStatements()->create($input);

        self::assertSame($bankBook->code, $statement->bankBook);
        self::assertSame((new DateTimeImmutable('today'))->format('Ymd'), $statement->date->format('Ymd'));
        self::assertSame('0.00', $statement->total()->value);
        self::assertTrue($statement->closeValue->equals($statement->startValue));
        self::assertCount(2, $statement->lines);
        self::assertSame(DebitCredit::Credit, $statement->lines[0]->debitCredit);
        self::assertSame('0.01', $statement->lines[0]->value->value);

        // What Twinfield answers for a duplicate is not documented: record it for TODO.md.
        $again = $this->twinfield->electronicBankStatements()->createMany([
            $input->number($statement->number),
        ]);

        fwrite(STDERR, sprintf(
            "Duplicate import: %s%s\n",
            $again->allCreated() ? 'created' : 'failed',
            $again->allCreated() ? '' : ': ' . $again->failed()[0]->message(),
        ));
        self::assertCount(1, $again);
    }

    private function bankBook(): BankBook
    {
        foreach ($this->twinfield->bankBooks()->search()->all() as $summary) {
            $bankBook = $this->twinfield->bankBooks()->get($summary->code);

            if ($bankBook->generalLedgerAccount !== null) {
                return $bankBook;
            }
        }

        self::markTestSkipped('The company has no bank book with a ledger account.');
    }
}
