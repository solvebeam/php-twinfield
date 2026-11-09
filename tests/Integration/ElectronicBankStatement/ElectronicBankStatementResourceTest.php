<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\ElectronicBankStatement;

use DateTimeImmutable;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatement;
use SolveBeam\Twinfield\ElectronicBankStatement\BankStatementAllocation;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Live BankStatementService queries (read-only) against TWINFIELD_COMPANY. Skipped when the
 * company has no bank statements in the last two weeks.
 */
final class ElectronicBankStatementResourceTest extends IntegrationTestCase
{
    public function testListsStatementsThatAddUp(): void
    {
        $from = new DateTimeImmutable('-14 days');
        $to = new DateTimeImmutable('today');
        $statements = $this->statements($from, $to);

        foreach ($statements as $statement) {
            $name = sprintf('Statement %s %d', $statement->bankBook, $statement->number);

            self::assertGreaterThanOrEqual($from->format('Y-m-d'), $statement->date->format('Y-m-d'), $name);
            self::assertLessThanOrEqual($to->format('Y-m-d'), $statement->date->format('Y-m-d'), $name);
            self::assertSame('00:00:00', $statement->date->format('H:i:s'), $name);
            self::assertSame(
                $statement->closingBalance->value,
                $statement->openingBalance->plus($statement->total())->value,
                $name . ': opening balance plus the lines is the closing balance.',
            );

            foreach ($statement->lines as $line) {
                $allocated = Amount::sum(array_map(
                    static fn (BankStatementAllocation $allocation): Amount => $allocation->signedAmount(),
                    $line->allocations,
                ));

                self::assertSame($line->amount->value, $allocated->value, $name . ' line ' . $line->lineId);
            }
        }
    }

    public function testFindsAStatementOnItsOwnDay(): void
    {
        $statement = $this->statements(new DateTimeImmutable('-14 days'), new DateTimeImmutable('today'))[0];

        // A time later on the day must not matter: list() sends the date only.
        $day = $statement->date->setTime(15, 0);
        $found = $this->twinfield->electronicBankStatements()->list($day, $day);

        self::assertContainsEquals($statement, $found);
    }

    public function testListsByCreationDate(): void
    {
        $this->statements(new DateTimeImmutable('-14 days'), new DateTimeImmutable('today'));

        $statements = $this->twinfield->electronicBankStatements()->listByCreationDate(
            from: new DateTimeImmutable('-14 days'),
        );

        self::assertNotSame([], $statements, 'Statements of the last two weeks were created in that time.');
    }

    public function testLeavesOutPostedStatements(): void
    {
        $from = new DateTimeImmutable('-14 days');
        $to = new DateTimeImmutable('today');
        $all = $this->statements($from, $to);
        $notPosted = $this->twinfield->electronicBankStatements()->list($from, $to, includePosted: false);

        self::assertEquals(
            array_values(array_filter($all, static fn (BankStatement $statement): bool => !$statement->isPosted())),
            $notPosted,
        );
    }

    public function testGivesNothingInTheFuture(): void
    {
        self::assertSame([], $this->twinfield->electronicBankStatements()->list(
            new DateTimeImmutable('+1 year'),
            new DateTimeImmutable('+1 year +1 month'),
        ));
    }

    /**
     * @return non-empty-list<BankStatement>
     */
    private function statements(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $statements = $this->twinfield->electronicBankStatements()->list($from, $to);

        if ($statements === []) {
            self::markTestSkipped('The company has no bank statements in the last two weeks.');
        }

        return $statements;
    }
}
