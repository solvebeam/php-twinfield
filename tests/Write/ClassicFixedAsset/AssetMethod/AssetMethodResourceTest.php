<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Write\ClassicFixedAsset\AssetMethod;

use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethod;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodAlreadyExistsException;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodInput;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodStatus;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\CalculationMethod;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\DepreciationStart;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\FreeTextType;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Tests\Support\WriteTestCase;

/**
 * Creates, updates, deletes and reactivates a temporary asset method SDKTEST9xx; always deletes it again.
 *
 * Uses the accounts of an existing asset method of the company, so they exist.
 */
final class AssetMethodResourceTest extends WriteTestCase
{
    public function testLifecycle(): void
    {
        $assetMethods = $this->twinfield->classicAssetMethods();
        $template = $this->existingMethod();
        $code = $this->unusedCode();

        try {
            $created = $assetMethods->create(
                AssetMethodInput::create($code, 'SDK test asset method')
                    ->shortName('SDK test')
                    ->linear(60)
                    ->depreciateFrom(DepreciationStart::FromPurchaseDate)
                    ->purchaseValueAccount($template->balanceAccounts->purchaseValue?->code)
                    ->cumulativeDepreciationAccount($template->balanceAccounts->depreciation?->code)
                    ->depreciationCostsAccount($template->profitLossAccounts->depreciation?->code)
                    ->freeText(1, 'Serial number')
                    ->freeText(2, 'Catalogue value', FreeTextType::Amount),
            );

            self::assertSame($code, $created->code);
            self::assertSame(AssetMethodStatus::Active, $created->status);
            self::assertFalse($created->inUse);
            self::assertSame('SDK test asset method', $created->name);
            self::assertSame('SDK test', $created->shortName);
            self::assertSame(CalculationMethod::Linear, $created->calculationMethod);
            self::assertSame(60, $created->periods);
            self::assertSame(DepreciationStart::FromPurchaseDate, $created->depreciationStart);
            self::assertSame(
                $template->balanceAccounts->purchaseValue?->code,
                $created->balanceAccounts->purchaseValue?->code,
            );
            self::assertSame('Serial number', $created->freeText(1)->label);
            self::assertSame(FreeTextType::Amount, $created->freeText(2)->type);

            try {
                $assetMethods->create(AssetMethodInput::create($code, 'Duplicate'));
                self::fail('Expected an AssetMethodAlreadyExistsException.');
            } catch (AssetMethodAlreadyExistsException) {
                self::assertSame(
                    'SDK test asset method',
                    $assetMethods->get($code)->name,
                    'The existing method is unchanged.',
                );
            }

            $updated = $assetMethods->update(
                $created->edit()
                    ->name('SDK test asset method (updated)')
                    ->shortName(null)
                    ->reducingBalance('20')
                    ->freeText(2, null),
            );

            self::assertSame('SDK test asset method (updated)', $updated->name);
            self::assertNull($updated->shortName, 'An update clears values that were removed.');
            self::assertSame(CalculationMethod::ReducingBalance, $updated->calculationMethod);
            self::assertSame(20.0, (float) $updated->percentage);
            self::assertNull($updated->freeText(2)->label);
            self::assertSame('Serial number', $updated->freeText(1)->label, 'edit() keeps other values.');
            self::assertSame(
                $template->profitLossAccounts->depreciation?->code,
                $updated->profitLossAccounts->depreciation?->code,
            );

            self::assertSame(DeleteResult::Deleted, $assetMethods->delete($code));
            self::assertSame(AssetMethodStatus::Deleted, $assetMethods->get($code)->status);

            $assetMethods->activate($code);
            self::assertSame(AssetMethodStatus::Active, $assetMethods->get($code)->status);
        } finally {
            if ($assetMethods->find($code)?->status === AssetMethodStatus::Active) {
                $assetMethods->delete($code);
            }
        }
    }

    private function existingMethod(): AssetMethod
    {
        foreach ($this->twinfield->classicAssetMethods()->search()->all() as $summary) {
            $method = $this->twinfield->classicAssetMethods()->get($summary->code);
            $balance = $method->balanceAccounts;

            if (
                $balance->purchaseValue !== null
                && $balance->depreciation !== null
                && $method->profitLossAccounts->depreciation !== null
            ) {
                return $method;
            }
        }

        self::markTestSkipped('The company has no asset method with purchase value, depreciation and costs accounts.');
    }

    private function unusedCode(): string
    {
        for ($number = 999; $number > 900; $number--) {
            $code = sprintf('SDKTEST%03d', $number);

            if ($this->twinfield->classicAssetMethods()->find($code) === null) {
                return $code;
            }
        }

        self::fail('No unused asset method code SDKTEST901–SDKTEST999.');
    }
}
