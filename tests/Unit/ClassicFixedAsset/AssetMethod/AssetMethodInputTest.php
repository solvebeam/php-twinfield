<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ClassicFixedAsset\AssetMethod;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\CalculationMethod;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\DepreciationStart;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\FreeTextType;

final class AssetMethodInputTest extends TestCase
{
    public function testBuildsTheDocumentedExample(): void
    {
        $input = AssetMethodInput::create('MACHINE', 'Machine')
            ->shortName('Machine')
            ->depreciateFrom(DepreciationStart::FromReconciliation)
            ->linear(60)
            ->purchaseValueAccount('0501')
            ->cumulativeDepreciationAccount('0135')
            ->depreciationCostsAccount('4730')
            ->freeText(1, 'Serial number');

        self::assertSame(
            '<assetmethod><office>001</office><code>MACHINE</code><name>Machine</name><shortname>Machine</shortname>'
            . '<calcmethod>linear</calcmethod><nrofperiods>60</nrofperiods>'
            . '<depreciatereconciliation>from_reconciliation</depreciatereconciliation>'
            . '<balanceaccounts><purchasevaluegroup/><purchasevalue>0501</purchasevalue><depreciationgroup/>'
            . '<depreciation>0135</depreciation><assetstoactivate/><reconciliation/><tobeinvoiced/></balanceaccounts>'
            . '<profitlossaccounts><depreciation>4730</depreciation><sales/><reconciliation/></profitlossaccounts>'
            . '<freetexts><freetext id="1" type="text">Serial number</freetext><freetext id="2" type="text"/>'
            . '<freetext id="3" type="text"/><freetext id="4" type="text"/><freetext id="5" type="text"/></freetexts>'
            . '</assetmethod>',
            $input->toXml('001')->toXml(),
        );
    }

    public function testSetsEveryAccount(): void
    {
        $xml = AssetMethodInput::create('MACHINE', 'Machine')
            ->purchaseValueGroup('GRP1')
            ->purchaseValueAccount('0501')
            ->depreciationGroup('GRP2')
            ->cumulativeDepreciationAccount('0135')
            ->assetsToActivateAccount('1701')
            ->revaluationAccount('0810')
            ->toBeInvoicedAccount('1702')
            ->depreciationCostsAccount('4730')
            ->disposalResultAccount('4980')
            ->revaluationReserveAccount('8520')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString(
            '<balanceaccounts><purchasevaluegroup>GRP1</purchasevaluegroup><purchasevalue>0501</purchasevalue>'
            . '<depreciationgroup>GRP2</depreciationgroup><depreciation>0135</depreciation>'
            . '<assetstoactivate>1701</assetstoactivate><reconciliation>0810</reconciliation>'
            . '<tobeinvoiced>1702</tobeinvoiced></balanceaccounts>'
            . '<profitlossaccounts><depreciation>4730</depreciation><sales>4980</sales>'
            . '<reconciliation>8520</reconciliation></profitlossaccounts>',
            $xml,
        );
    }

    public function testNullClearsAnAccountAndAFreeText(): void
    {
        $xml = AssetMethodInput::create('MACHINE', 'Machine')
            ->purchaseValueAccount('0501')
            ->purchaseValueAccount(null)
            ->freeText(3, 'Location', FreeTextType::Amount)
            ->freeText(3, null)
            ->shortName('')
            ->toXml('1000')
            ->toXml();

        self::assertStringContainsString('<shortname/>', $xml);
        self::assertStringContainsString('<purchasevalue/>', $xml);
        self::assertStringContainsString('<freetext id="3" type="text"/>', $xml);
    }

    public function testLeavesOutCalculationSettingsThatAreNotSet(): void
    {
        $xml = AssetMethodInput::create('MACHINE', 'Machine')->toXml('1000')->toXml();

        foreach (['calcmethod', 'nrofperiods', 'percentage', 'depreciatereconciliation'] as $element) {
            self::assertStringNotContainsString($element, $xml);
        }
    }

    /**
     * @return iterable<string, array{Closure(AssetMethodInput): AssetMethodInput, CalculationMethod, ?int, ?string}>
     */
    public static function calculations(): iterable
    {
        yield 'linear' => [static fn (AssetMethodInput $input) => $input->linear(36), CalculationMethod::Linear, 36, null];
        yield 'linear percentage' => [
            static fn (AssetMethodInput $input) => $input->linearPercentage('20'),
            CalculationMethod::LinearPercentage,
            null,
            '20',
        ];
        yield 'reducing balance' => [
            static fn (AssetMethodInput $input) => $input->reducingBalance('12.5'),
            CalculationMethod::ReducingBalance,
            null,
            '12.5',
        ];
        yield 'none' => [static fn (AssetMethodInput $input) => $input->noDepreciation(), CalculationMethod::None, null, null];
        yield 'last one wins' => [
            static fn (AssetMethodInput $input) => $input->reducingBalance('25')->linear(12),
            CalculationMethod::Linear,
            12,
            null,
        ];
    }

    /**
     * @param Closure(AssetMethodInput): AssetMethodInput $configure
     */
    #[DataProvider('calculations')]
    public function testSetsTheCalculation(Closure $configure, CalculationMethod $method, ?int $periods, ?string $percentage): void
    {
        $input = $configure(AssetMethodInput::create('MACHINE', 'Machine'));

        self::assertSame($method, $input->calculationMethod);
        self::assertSame($periods, $input->periods);
        self::assertSame($percentage, $input->percentage);
    }

    public function testIsImmutable(): void
    {
        $input = AssetMethodInput::create('MACHINE', 'Machine');
        $input->name('Other')->linear(60);

        self::assertSame('Machine', $input->name);
        self::assertNull($input->calculationMethod);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'invalid code' => [static fn () => AssetMethodInput::create('machine', 'Machine'), 'is not a code'];
        yield 'empty name' => [static fn () => AssetMethodInput::create('MACHINE', ''), 'name must not be empty'];
        yield 'zero periods' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->linear(0), 'periods must be 1 or more'];
        yield 'comma percentage' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->reducingBalance('12,5'), 'Percentage must be'];
        yield 'zero percentage' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->linearPercentage('0'), 'Percentage must be'];
        yield 'too high percentage' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->linearPercentage('101'), 'Percentage must be'];
        yield 'invalid account' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->purchaseValueAccount('05 01'), 'is not a code'];
        yield 'free text 0' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->freeText(0, 'X'), 'id must be 1 to 5'];
        yield 'free text 6' => [static fn () => AssetMethodInput::create('MACHINE', 'M')->freeText(6, 'X'), 'id must be 1 to 5'];
    }

    /**
     * @param Closure(): mixed $create
     */
    #[DataProvider('invalidInput')]
    public function testRejectsInvalidInput(Closure $create, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $create();
    }
}
