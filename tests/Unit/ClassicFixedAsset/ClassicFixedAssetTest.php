<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ClassicFixedAsset;

use DateTimeImmutable;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethodReference;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAsset;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetField;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetStatus;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ClassicFixedAssetTest extends TestCase
{
    public function testReadsAnActiveAsset(): void
    {
        $asset = self::fixture('fixed-asset-read');

        self::assertEquals(TestTokens::company('1000'), $asset->company);
        self::assertSame('60010', $asset->code);
        self::assertSame('a3b86cfc-62ed-44cf-b1fe-bfb1ddb30e37', $asset->uid);
        self::assertSame('Delivery van', $asset->name);
        self::assertSame(DimensionStatus::Active, $asset->status);
        self::assertTrue($asset->inUse);
        self::assertSame(DimensionBehaviour::Normal, $asset->behaviour);
        self::assertSame(3, $asset->touched);
        self::assertEquals(new DateTimeImmutable('2026-10-15 09:30:15'), $asset->modified);
        self::assertEquals(new DimensionReference('00000', 'KPL', 'General'), $asset->costCenter);
        self::assertNull($asset->vatCode);
        self::assertSame(['VEHICLES'], $asset->groups);

        self::assertSame(ClassicFixedAssetStatus::Active, $asset->assetStatus);
        self::assertEquals(new AssetMethodReference('5YVEH', '5 years linear - vehicles', '5YVEH'), $asset->method);
        self::assertSame(60, $asset->periods);
        self::assertFalse($asset->periodsInherited);
        self::assertNull($asset->percentage);
        self::assertSame('2500.00', $asset->residualValue->value);
        self::assertSame('0.00', $asset->stopValue->value);
        self::assertSame('2026/04', (string) $asset->depreciateFrom);
        self::assertSame('2026/09', (string) $asset->lastDepreciation);
        self::assertEquals(new DateTimeImmutable('2026-03-15'), $asset->purchaseDate);
        self::assertNull($asset->sellDate);
        self::assertSame('WDB9066331S123456', $asset->freeText(1));
        self::assertNull($asset->freeText(2));
        self::assertSame([1 => 'WDB9066331S123456', 2 => null, 3 => null, 4 => null, 5 => null], $asset->freeTexts);

        self::assertSame([
            ClassicFixedAssetField::Status,
            ClassicFixedAssetField::DepreciateFrom,
            ClassicFixedAssetField::SellDate,
            ClassicFixedAssetField::TransactionLines,
            ClassicFixedAssetField::LastDepreciation,
        ], $asset->lockedFields);
        self::assertTrue($asset->isLocked(ClassicFixedAssetField::DepreciateFrom));
        self::assertFalse($asset->isLocked(ClassicFixedAssetField::ResidualValue));
    }

    public function testReadsTheTransactionLines(): void
    {
        $lines = self::fixture('fixed-asset-read')->transactionLines;

        self::assertCount(2, $lines);

        $purchase = $lines[0];

        self::assertSame('INK', $purchase->transactionType);
        self::assertSame('Purchase', $purchase->transactionTypeName);
        self::assertSame(202600012, $purchase->number);
        self::assertSame(2, $purchase->line);
        self::assertSame('42000.00', $purchase->amount->value);
        self::assertSame('2026/03', (string) $purchase->period);
        self::assertTrue($purchase->inUse);
        self::assertTrue($purchase->checked);
        self::assertTrue($purchase->locked);
        self::assertEquals(new DimensionReference('0300', 'BAS', 'Investment vehicles'), $purchase->account());
        self::assertEquals(new DimensionReference('00000', 'KPL', 'General'), $purchase->costCenter());
        self::assertEquals(new DimensionReference('60010', 'AST', 'Delivery van'), $purchase->dimension(3));
        self::assertNull($purchase->dimension(4));
        self::assertSame([1, 2, 3], array_keys($purchase->dimensions));

        self::assertSame('1250.50', $lines[1]->amount->value);
    }

    public function testReadsASoldAsset(): void
    {
        $asset = self::fixture('fixed-asset-read-sold');

        self::assertSame(ClassicFixedAssetStatus::Sold, $asset->assetStatus);
        self::assertEquals(new DateTimeImmutable('2024-09-12'), $asset->sellDate);
        self::assertSame('2017/13', (string) $asset->transactionLines[0]->period);
        self::assertSame('-4242.00', $asset->transactionLines[1]->amount->value);
        self::assertFalse($asset->transactionLines[1]->inUse);
        self::assertSame(['INV'], $asset->groups);
    }

    public function testReadsAnAssetToBeActivated(): void
    {
        $asset = self::fixture('fixed-asset-read-to-be-activated');

        self::assertSame(ClassicFixedAssetStatus::ToBeActivated, $asset->assetStatus);
        self::assertFalse($asset->inUse);
        self::assertSame('IH', $asset->vatCode);
        // Weekly periods.
        self::assertSame('2015/48', (string) $asset->depreciateFrom);
        self::assertSame(260, $asset->periods);
        self::assertSame('12.5', $asset->percentage);
        self::assertNull($asset->lastDepreciation);
        self::assertSame('Kitchen', $asset->freeText(3));
        self::assertSame([], $asset->groups, 'Twinfield leaves out <groups> when empty.');
        self::assertSame([], $asset->transactionLines);
        self::assertSame([ClassicFixedAssetField::Status, ClassicFixedAssetField::SellDate], $asset->lockedFields);
    }

    public function testReadsAnAssetWithoutMethod(): void
    {
        $asset = self::fixture('fixed-asset-read-without-method');

        self::assertNull($asset->method);
        self::assertNull($asset->periods);
        self::assertTrue($asset->periodsInherited);
        self::assertNull($asset->depreciateFrom);
        self::assertNull($asset->purchaseDate);
    }

    public function testReadsTheDocumentedSaveResponse(): void
    {
        $asset = self::fixture('fixed-asset-save');

        self::assertSame('60011', $asset->code);
        self::assertNull($asset->modified);
        self::assertEquals(new DimensionReference('00000', null, 'General'), $asset->costCenter);
        self::assertSame([ClassicFixedAssetField::Status, ClassicFixedAssetField::SellDate], $asset->lockedFields);
        self::assertSame('WDB9066331S123456', $asset->freeText(1));
    }

    public function testThrowsOnAnUnknownStatus(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'dimension/fixedassets/status is "stolen", expected one of tobeactivated, active, inactive, sold.',
        );

        self::fixture('fixed-asset-read-unknown-status');
    }

    public function testThrowsOnAnotherDimensionType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/type is "KPL", expected AST.');

        self::fixture('fixed-asset-read-wrong-type');
    }

    public function testThrowsOnAnInvalidPeriod(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/fixedassets/beginperiod is "April", expected a period yyyy/pp.');

        self::fixture('fixed-asset-read', static fn (string $xml): string => str_replace(
            '<beginperiod locked="true">2026/04</beginperiod>',
            '<beginperiod locked="true">April</beginperiod>',
            $xml,
        ));
    }

    public function testThrowsOnAnInvalidPercentage(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/fixedassets/percentage is "12,5", expected a percentage.');

        self::fixture('fixed-asset-read-to-be-activated', static fn (string $xml): string => str_replace(
            '>12.5<',
            '>12,5<',
            $xml,
        ));
    }

    public function testThrowsOnAnotherCostCenterLevel(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/financials/substitutionlevel is "1", expected 2 (the cost center level).');

        self::fixture('fixed-asset-read', static fn (string $xml): string => str_replace(
            '<substitutionlevel>2</substitutionlevel>',
            '<substitutionlevel>1</substitutionlevel>',
            $xml,
        ));
    }

    public function testThrowsOnAMethodWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/fixedassets/method/@name is empty.');

        self::fixture('fixed-asset-read', static fn (string $xml): string => str_replace(
            'name="5 years linear - vehicles"',
            'name=""',
            $xml,
        ));
    }

    public function testThrowsOnAnInvalidLockedAttribute(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/fixedassets/residualvalue/@locked is "yes", expected a boolean');

        self::fixture('fixed-asset-read', static fn (string $xml): string => str_replace(
            '<residualvalue locked="false">',
            '<residualvalue locked="yes">',
            $xml,
        ));
    }

    public function testThrowsOnATransactionLineWithoutNumber(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('dimension/fixedassets/translines/transline[1]/number is "", expected an integer.');

        self::fixture('fixed-asset-read', static fn (string $xml): string => str_replace(
            '<number locked="true">202600012</number>',
            '<number locked="true"></number>',
            $xml,
        ));
    }

    public function testRejectsAnUnknownFreeTextField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Free text field id must be 1 to 5, 6 given.');

        self::fixture('fixed-asset-read')->freeText(6);
    }

    /**
     * @param ?callable(string): string $change Changes the inner XML before parsing.
     */
    public static function fixture(string $name, ?callable $change = null): ClassicFixedAsset
    {
        $document = new DOMDocument();
        $document->load(dirname(__DIR__, 2) . '/Fixtures/soap/' . $name . '.xml');

        $xml = (string) $document->getElementsByTagName('ProcessXmlStringResult')->item(0)?->textContent;
        $xml = $change === null ? $xml : $change($xml);

        return ClassicFixedAsset::fromXml(XmlElement::root(ProcessXmlResult::fromXml($xml)), TestTokens::organisation());
    }
}
