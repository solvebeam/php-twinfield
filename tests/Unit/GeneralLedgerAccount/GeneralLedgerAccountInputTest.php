<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\GeneralLedgerAccount;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidationType;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

final class GeneralLedgerAccountInputTest extends TestCase
{
    public function testSendsOnlyTheCodeAndName(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>BAS</type><code>1310</code><name>Debiteuren</name></dimension>',
            GeneralLedgerAccountInput::balanceSheet('1310', 'Debiteuren')->toXml('1000')->toXml(),
        );
    }

    public function testSendsEverySetting(): void
    {
        $input = GeneralLedgerAccountInput::profitAndLoss('8010', 'Omzet diensten')
            ->shortName('Diensten')
            ->activeFrom(FinancialPeriod::of(2026, 1))
            ->activeUntil(FinancialPeriod::of(2027, 12))
            ->matchable()
            ->subAnalysis(SubAnalysis::Required)
            ->vatCode('VH', fixed: true)
            ->childValidation(ChildValidation::dimensionType('KPL'))
            ->childValidation(ChildValidation::group('REVENUE'))
            ->group('REVENUE')
            ->group('SERVICES');

        self::assertSame(GeneralLedgerAccountType::ProfitAndLoss, $input->type);
        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8010</code><name>Omzet diensten</name>'
                . '<shortname>Diensten</shortname><beginperiod>1</beginperiod><beginyear>2026</beginyear>'
                . '<endperiod>12</endperiod><endyear>2027</endyear>'
                . '<financials><matchtype>matchable</matchtype><subanalyse>true</subanalyse>'
                . '<vatcode fixed="true">VH</vatcode><childvalidations>'
                . '<childvalidation type="type" level="2">KPL</childvalidation>'
                . '<childvalidation type="group" level="0">REVENUE</childvalidation>'
                . '</childvalidations></financials>'
                . '<groups><group>REVENUE</group><group>SERVICES</group></groups></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testSendsOnlyChildValidationsInFinancials(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8000</code><name>Omzet</name>'
                . '<financials><childvalidations><childvalidation type="code" level="2">00100</childvalidation>'
                . '</childvalidations></financials></dimension>',
            GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet')
                ->childValidations(ChildValidation::dimension('00100', 2))
                ->toXml('1000')
                ->toXml(),
        );
    }

    public function testClearsValuesAndLists(): void
    {
        $input = GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet')
            ->shortName('Omzet')
            ->vatCode('VH')
            ->group('REVENUE')
            ->childValidation(ChildValidation::dimensionType('KPL'))
            ->shortName(null)
            ->vatCode(null)
            ->activeFrom(null)
            ->matchable(false)
            ->groups()
            ->childValidations();

        self::assertTrue($input->has('shortname'));
        self::assertNull($input->value('shortname'));
        self::assertSame('notmatchable', $input->value('financials/matchtype'));
        self::assertSame([], $input->groups);
        self::assertSame([], $input->childValidations);
        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8000</code><name>Omzet</name>'
                . '<shortname/><beginperiod>0</beginperiod><beginyear>0</beginyear>'
                . '<financials><matchtype>notmatchable</matchtype><vatcode fixed="false"/><childvalidations/></financials>'
                . '<groups/></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testIgnoresDuplicates(): void
    {
        $input = GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet')
            ->group('REVENUE')
            ->group('REVENUE')
            ->childValidation(ChildValidation::dimensionType('KPL'))
            ->childValidation(ChildValidation::dimensionType('KPL'));

        self::assertSame(['REVENUE'], $input->groups);
        self::assertCount(1, $input->childValidations ?? []);
    }

    public function testIsImmutable(): void
    {
        $input = GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet');
        $changed = $input->name('Revenue')->subAnalysis(SubAnalysis::Optional);

        self::assertSame('Omzet', $input->name);
        self::assertFalse($input->has('financials/subanalyse'));
        self::assertSame('Revenue', $changed->name);
        self::assertSame('maybe', $changed->value('financials/subanalyse'));
    }

    public function testOfUsesTheGivenType(): void
    {
        self::assertSame(
            GeneralLedgerAccountType::BalanceSheet,
            GeneralLedgerAccountInput::of(GeneralLedgerAccountType::BalanceSheet, '1310', 'Debiteuren')->type,
        );
    }

    public function testRefusesAnInvalidCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"80 10" is not a code');

        GeneralLedgerAccountInput::profitAndLoss('80 10', 'Omzet');
    }

    public function testRefusesAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('General ledger account name must not be empty.');

        GeneralLedgerAccountInput::profitAndLoss('8000', '');
    }

    public function testRefusesALongShortName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('General ledger account short name must be at most 20 characters, 21 given');

        GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet')->shortName(str_repeat('a', 21));
    }

    public function testRefusesAFixedVatCodeWithoutVatCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A fixed VAT code needs a VAT code.');

        GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet')->vatCode(null, fixed: true);
    }

    public function testChildValidationLevels(): void
    {
        self::assertSame(2, ChildValidation::dimensionType('DEB')->level);
        self::assertSame(3, ChildValidation::dimensionType('PRJ')->level);
        self::assertSame(4, ChildValidation::dimensionType('ACT')->level);
        self::assertSame(2, ChildValidation::dimensionType('XYZ', 2)->level);
        self::assertSame(0, ChildValidation::group('REVENUE')->level);
        self::assertSame(ChildValidationType::Code, ChildValidation::dimension('1000', 2)->type);
    }

    public function testChildValidationRefusesAnUnknownTypeWithoutLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown dimension type "XYZ": pass its level, e.g. dimensionType(\'XYZ\', 2).');

        ChildValidation::dimensionType('XYZ');
    }

    public function testChildValidationRefusesLevelZeroForADimension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Child validation level 0 is only allowed for a dimension group.');

        ChildValidation::dimension('1000', 0);
    }

    public function testChildValidationRefusesALevelAboveFour(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Child validation level must be 0 to 4, 5 given.');

        ChildValidation::group('REVENUE', 5);
    }
}
