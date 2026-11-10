<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A fixed asset class of the new fixed assets module (e.g. buildings or vehicles): the default
 * depreciation and the accounts of its assets. Accounts are GUIDs.
 */
final readonly class FixedAssetClass
{
    /**
     * @param list<ClassDepreciationSetting> $depreciationSettings Per regime.
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the class. */
        public string $id,
        /** Code, e.g. `GEBOUWEN`. */
        public string $code,
        public string $name,
        public ClassActivationStatus $activationStatus,
        /** True when assets use the class. */
        public bool $used,
        public array $depreciationSettings,
        public string $purchaseValueAccountId,
        public string $depreciationBalanceAccountId,
        public string $depreciationProfitAndLossAccountId,
        public string $disposalBalanceAccountId,
        public string $disposalResultAccountId,
        public string $revaluationBalanceAccountId,
        public string $revaluationProfitAndLossAccountId,
        public string $revaluationDepreciationBalanceAccountId,
        public string $revaluationDepreciationProfitAndLossAccountId,
        public int $version,
    ) {
        Guid::of($id);

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('Fixed asset class code and name must not be empty.');
        }
    }
}
