<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A fixed asset of the new fixed assets module (REST API), read with `fixedAssets()->get($id)`.
 *
 * Classes, cost centers, locations and asset types change over time: each has the period or year from
 * which it applies. References to other data are GUIDs, e.g. `$asset->costCenters[0]->id` is the `uid`
 * of the cost center (`$twinfield->ledgerAccounts()->get($id)` gives its code).
 */
final readonly class FixedAsset
{
    /**
     * @param list<string>                      $transactionIds GUIDs of the linked transactions.
     * @param list<DepreciationSettings>        $depreciationSettings Per regime.
     * @param array<int, string>                $freeTexts Free text fields 1 to 5 that have a value, by number.
     * @param list<TimeRelatedReference>        $locations
     * @param list<FixedAssetPartner>           $partners
     * @param list<FixedAssetTypes>             $assetTypes
     * @param list<string>                      $attachmentIds
     * @param array<string, int>                $currentPeriodNumbers Number of the current period of the asset
     *                                                                per regime, by Regime value.
     * @param list<FixedAssetPurchaseValue>     $purchaseValues
     * @param list<YearRelatedReference>        $classes
     * @param list<TimeRelatedReference>        $costCenters
     * @param array<string, int>                $groups Year from which the asset is in each group, by GUID.
     * @param list<FixedAssetLink>              $parents
     * @param list<FixedAssetLink>              $children
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the asset. */
        public string $id,
        /** Code, e.g. `60000`. */
        public string $code,
        public string $description,
        public FixedAssetStatus $status,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        /** GUID of the supplier; null when not set. */
        public ?string $supplierId,
        /** GUID of the project; null when not set. */
        public ?string $projectId,
        public array $transactionIds,
        /** Next period to depreciate; null when the asset is not depreciated (any more). */
        public ?Period $nextPeriodToDepreciate,
        public array $depreciationSettings,
        public DateTimeImmutable $investmentDate,
        public DateTimeImmutable $firstUseDate,
        public array $freeTexts,
        public ?string $licensePlate,
        public ?string $serialNumber,
        /** GUID of the tax reason, e.g. for investment allowance; null when there is none. */
        public ?string $taxReasonId,
        public ?Amount $taxReasonAmount,
        public array $locations,
        public array $partners,
        public array $assetTypes,
        public array $attachmentIds,
        public array $currentPeriodNumbers,
        public array $purchaseValues,
        public array $classes,
        public array $costCenters,
        public array $groups,
        /** True when Twinfield imported the history of the asset instead of calculating it. */
        public bool $isImportedWithMimicHistory,
        /** Last period of the imported history; null without one. */
        public ?Period $lastMimicPeriod,
        public ?Period $lastRevaluationPeriod,
        public array $parents,
        public array $children,
        /** Company the asset was sold to (intercompany); null when not. */
        public ?string $targetCompany,
        /** Company the asset was bought from (intercompany); null when not. */
        public ?string $sourceCompany,
        /** Balance sheet account of the purchase value. */
        public AccountReference $purchaseValueAccount,
        /** Book value of the youngest regime. */
        public Amount $netBookValue,
        /** Purchase value of the youngest regime. */
        public Amount $purchaseValue,
        public int $version,
    ) {
        Guid::of($id);

        if ($code === '') {
            throw new InvalidArgumentException('Fixed asset code must not be empty.');
        }
    }

    /**
     * Depreciation settings of one regime; null when the asset has none for it.
     */
    public function depreciation(Regime $regime): ?DepreciationSettings
    {
        foreach ($this->depreciationSettings as $settings) {
            if ($settings->regime === $regime) {
                return $settings;
            }
        }

        return null;
    }

    /**
     * Free text field 1 to 5; null when empty.
     */
    public function freeText(int $number): ?string
    {
        if ($number < 1 || $number > 5) {
            throw new InvalidArgumentException(sprintf('Free text field must be 1 to 5, %d given.', $number));
        }

        return $this->freeTexts[$number] ?? null;
    }
}
