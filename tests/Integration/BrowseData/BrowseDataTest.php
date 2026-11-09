<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\BrowseData;

use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\BrowseFieldDataType;
use SolveBeam\Twinfield\BrowseData\BrowseOperator;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live browse data (read-only) against TWINFIELD_COMPANY.
 */
final class BrowseDataTest extends IntegrationTestCase
{
    public function testReadsBrowseDefinitions(): void
    {
        $codes = [
            BrowseCode::GeneralLedgerTransactions,
            BrowseCode::GeneralLedgerDetailsV3,
            BrowseCode::AnnualReportTotals,
        ];

        foreach ($codes as $code) {
            $definition = $this->twinfield->browseDefinitions()->get($code);

            self::assertSame($code->value, $definition->code);
            self::assertTrue($definition->company->equals($this->twinfield->companyReference()));
            self::assertNotSame([], $definition->criteria());
        }

        $period = $this->twinfield->browseDefinitions()->get('030_3')->column('fin.trs.head.yearperiod');

        self::assertNotNull($period);
        self::assertTrue($period->ask);
        self::assertSame(BrowseOperator::Between, $period->operator);
    }

    public function testThrowsForAnUnknownBrowseCode(): void
    {
        $this->expectException(ProcessXmlException::class);

        $this->twinfield->browseDefinitions()->get('999');
    }

    public function testListsBrowseFields(): void
    {
        $fields = $this->twinfield->browseDefinitions()->fields();

        $status = null;

        foreach ($fields as $field) {
            if ($field->code === 'fin.trs.head.status') {
                $status = $field;
            }
        }

        self::assertGreaterThan(100, count($fields));
        self::assertNotNull($status);
        self::assertSame(BrowseFieldDataType::Special, $status->dataType);
        self::assertContains('normal', array_map(static fn ($option) => $option->value, $status->options));
    }

    public function testReadsTransactionLines(): void
    {
        $year = (int) date('Y') - 1;

        $result = $this->twinfield->browse(BrowseCode::GeneralLedgerDetailsV3)
            ->columns(
                'fin.trs.head.code',
                'fin.trs.head.number',
                'fin.trs.head.date',
                'fin.trs.head.modified',
                'fin.trs.head.status',
                'fin.trs.line.dim1',
                'fin.trs.line.valuesigned',
                'fin.trs.line.description',
            )
            ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
            ->equal('fin.trs.head.status', 'normal')
            ->get();

        if (count($result) === 0) {
            self::markTestSkipped(sprintf('The company has no transactions in %d.', $year));
        }

        foreach ($result as $row) {
            self::assertNotNull($row->transactionLine);
            self::assertFalse($row->transactionLine->isDraft(), 'Status normal has no drafts.');
            self::assertSame($row->string('fin.trs.head.code'), $row->transactionLine->dayBook);
            self::assertSame($row->int('fin.trs.head.number'), $row->transactionLine->number);
            self::assertContains($row->string('fin.trs.head.status'), ['temporary', 'final']);
            $row->date('fin.trs.head.date');
            $row->amount('fin.trs.line.valuesigned');
            $row->dateTime('fin.trs.head.modified');
            $row->nullableString('fin.trs.line.description');
        }
    }

    public function testReadsBalancesPerGeneralLedgerAccount(): void
    {
        $year = (int) date('Y') - 1;

        $result = $this->twinfield->browse(BrowseCode::AnnualReportTotals)
            ->columns('fin.trs.line.dim1', 'fin.trs.line.basevaluesigned')
            ->between('fin.trs.head.yearperiod', sprintf('%d/00', $year), sprintf('%d/55', $year))
            ->equal('fin.trs.head.balanceregimeprompt', Regime::Generic)
            ->get();

        foreach ($result as $row) {
            self::assertNull($row->transactionLine, 'Totals have no transaction line.');
            $row->amount('fin.trs.line.basevaluesigned');
        }

        $this->addToAssertionCount(1);
    }

    public function testThrowsForAnUnknownField(): void
    {
        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('fin.trs.nope');

        $this->twinfield->browse('030_3')
            ->columns('fin.trs.nope')
            ->between('fin.trs.head.yearperiod', '2025/00', '2025/55')
            ->get();
    }
}
