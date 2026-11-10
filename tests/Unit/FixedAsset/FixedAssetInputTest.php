<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\FixedAsset;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\FixedAsset\FixedAssetField;
use SolveBeam\Twinfield\FixedAsset\FixedAssetInput;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Period\Period;

final class FixedAssetInputTest extends TestCase
{
    public function testANewAssetOnlySendsTheName(): void
    {
        self::assertSame(
            '<dimension><office>1000</office><type>AST</type><name>Delivery van</name></dimension>',
            FixedAssetInput::create('Delivery van')->toXml('1000')->toXml(),
        );
    }

    public function testBuildsAFullAssetInTheOrderOfTheDocumentation(): void
    {
        $input = FixedAssetInput::create('Delivery van & trailer')
            ->code('60010')
            ->group('VEHICLES')
            ->periods(60)
            ->freeText(1, 'WDB9066331S123456')
            ->purchaseDate(new DateTimeImmutable('2026-03-15'))
            ->depreciateFrom(Period::of(2026, 4))
            ->method('5YVEH')
            ->stopValue('0')
            ->residualValue(Amount::of('2500'))
            ->vatCode('IH')
            ->costCenter('00000')
            ->percentage('12.5')
            ->lastDepreciation(Period::of(2026, 3));

        self::assertSame(
            '<dimension><office>1000</office><type>AST</type><code>60010</code>'
            . '<name>Delivery van &amp; trailer</name>'
            . '<financials><substitutionlevel>2</substitutionlevel><substitutewith>00000</substitutewith>'
            . '<vatcode>IH</vatcode></financials>'
            . '<fixedassets><residualvalue>2500.00</residualvalue><stopvalue>0.00</stopvalue><method>5YVEH</method>'
            . '<beginperiod>2026/04</beginperiod><purchasedate>20260315</purchasedate>'
            . '<freetext1>WDB9066331S123456</freetext1><nrofperiods>60</nrofperiods><percentage>12.5</percentage>'
            . '<lastdepreciation>2026/03</lastdepreciation></fixedassets>'
            . '<groups><group>VEHICLES</group></groups></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testNullSendsAnEmptyElement(): void
    {
        $input = FixedAssetInput::create('Delivery van')
            ->vatCode(null)
            ->periods(null)
            ->freeText(2, '')
            ->purchaseDate(null);

        self::assertTrue($input->has('fixedassets/nrofperiods'));
        self::assertNull($input->value('fixedassets/nrofperiods'));
        self::assertFalse($input->has('fixedassets/percentage'));
        self::assertSame(
            '<dimension><office>1000</office><type>AST</type><name>Delivery van</name>'
            . '<financials><vatcode/></financials>'
            . '<fixedassets><purchasedate/><freetext2/><nrofperiods/></fixedassets></dimension>',
            $input->toXml('1000')->toXml(),
        );
    }

    public function testIsImmutable(): void
    {
        $input = FixedAssetInput::create('Delivery van');
        $changed = $input->periods(60)->group('VEHICLES');

        self::assertFalse($input->has('fixedassets/nrofperiods'));
        self::assertNull($input->groups);
        self::assertSame('60', $changed->value('fixedassets/nrofperiods'));
        self::assertSame(['VEHICLES'], $changed->groups);
    }

    public function testGroups(): void
    {
        $input = FixedAssetInput::create('Delivery van')->group('VEHICLES')->group('LEASE')->group('VEHICLES');

        self::assertSame(['VEHICLES', 'LEASE'], $input->groups);
        self::assertSame([], $input->groups()->groups, 'groups() without arguments removes them.');
        self::assertStringEndsWith('<groups/></dimension>', $input->groups()->toXml('1000')->toXml());
    }

    public function testCodeNullLetsTwinfieldChoose(): void
    {
        self::assertNull(FixedAssetInput::create('Delivery van')->code('60010')->code('')->code);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'empty name' => [static fn () => FixedAssetInput::create(''), 'Fixed asset name must not be empty.'];
        yield 'long name' => [
            static fn () => FixedAssetInput::create(str_repeat('a', 81)),
            'Fixed asset name must be at most 80 characters',
        ];
        yield 'invalid code' => [static fn () => FixedAssetInput::create('Van')->code('van 1'), '"van 1" is not a code'];
        yield 'invalid method' => [static fn () => FixedAssetInput::create('Van')->method('5 years'), '"5 years" is not a code'];
        yield 'zero periods' => [
            static fn () => FixedAssetInput::create('Van')->periods(0),
            'Number of periods must be 1 or more, 0 given.',
        ];
        yield 'percentage with comma' => [
            static fn () => FixedAssetInput::create('Van')->percentage('12,5'),
            'Percentage must be a number from 0 to 100 with a dot, e.g. "12.5", "12,5" given.',
        ];
        yield 'percentage over 100' => [
            static fn () => FixedAssetInput::create('Van')->percentage('120'),
            'Percentage must be a number from 0 to 100',
        ];
        yield 'negative residual value' => [
            static fn () => FixedAssetInput::create('Van')->residualValue('-1'),
            'The residual value must not be negative, -1.00 given.',
        ];
        yield 'float-like amount' => [
            static fn () => FixedAssetInput::create('Van')->stopValue('1,50'),
            '"1,50" is not an amount',
        ];
        yield 'free text 6' => [
            static fn () => FixedAssetInput::create('Van')->freeText(6, 'x'),
            'Free text field id must be 1 to 5, 6 given.',
        ];
        yield 'invalid group' => [static fn () => FixedAssetInput::create('Van')->group('my group'), '"my group" is not a code'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    public function testEditKeepsWhatMayBeChangedAndLeavesOutLockedSettings(): void
    {
        $input = FixedAssetTest::fixture('fixed-asset-read')->edit();

        self::assertSame('60010', $input->code);
        self::assertSame(
            '<dimension><office>1000</office><type>AST</type><code>60010</code><name>Delivery van</name>'
            . '<financials><substitutionlevel>2</substitutionlevel><substitutewith>00000</substitutewith>'
            . '<vatcode/></financials>'
            . '<fixedassets><residualvalue>2500.00</residualvalue><stopvalue>0.00</stopvalue><method>5YVEH</method>'
            . '<purchasedate>20260315</purchasedate><freetext1>WDB9066331S123456</freetext1><freetext2/>'
            . '<freetext3/><freetext4/><freetext5/><nrofperiods>60</nrofperiods><percentage/></fixedassets>'
            . '<groups><group>VEHICLES</group></groups></dimension>',
            $input->toXml('1000')->toXml(),
        );
        self::assertTrue($input->isLocked(FixedAssetField::DepreciateFrom));
    }

    public function testSettingALockedSettingThrows(): void
    {
        $input = FixedAssetTest::fixture('fixed-asset-read')->edit();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'The begin period of fixed asset 60010 is locked: Twinfield does not allow changing it, '
            . 'e.g. because the asset is active.',
        );

        $input->depreciateFrom(Period::of(2026, 5));
    }

    public function testEditOfAnAssetToBeActivatedKeepsEverything(): void
    {
        $input = FixedAssetTest::fixture('fixed-asset-read-to-be-activated')->edit();

        self::assertSame('2015/48', $input->value('fixedassets/beginperiod'));
        self::assertSame('260', $input->value('fixedassets/nrofperiods'));
        self::assertSame('12.5', $input->value('fixedassets/percentage'));
        self::assertSame('IH', $input->value('financials/vatcode'));
        self::assertSame('Kitchen', $input->value('fixedassets/freetext3'));
        self::assertTrue($input->has('fixedassets/lastdepreciation'));
        self::assertNull($input->groups, 'No groups: <groups> is not sent.');

        // Unlocked, so it may be changed.
        self::assertSame('2016/01', $input->depreciateFrom(Period::of(2016, 1))->value('fixedassets/beginperiod'));
    }

    public function testEditKeepsInheritedPeriodsInherited(): void
    {
        $input = FixedAssetTest::fixture('fixed-asset-read-without-method')->edit();

        self::assertFalse($input->has('fixedassets/nrofperiods'));
        self::assertFalse($input->has('fixedassets/method'));
    }
}
