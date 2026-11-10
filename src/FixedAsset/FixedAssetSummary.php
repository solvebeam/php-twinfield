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
 * A fixed asset in a list of the new fixed assets module (`fixedAssets()->search()`). `get()` reads
 * the whole asset.
 */
final readonly class FixedAssetSummary
{
    public function __construct(
        public CompanyReference $company,
        /** GUID of the asset. */
        public string $id,
        /** Code, e.g. `60000`. */
        public string $code,
        public string $description,
        public FixedAssetStatus $status,
        /** Why Twinfield could not post the last depreciation; null when it could. */
        public ?string $postingErrorMessage,
        /** Last depreciated period; null when the asset was not depreciated yet. */
        public ?Period $lastDepreciatedPeriod,
        public DateTimeImmutable $investmentDate,
        public DateTimeImmutable $firstUseDate,
        /** Null until the asset is disposed. */
        public ?DateTimeImmutable $disposeDate,
        /** Zero until the asset is disposed. */
        public Amount $disposePrice,
        /** Book value of the youngest regime. */
        public Amount $netBookValue,
        /** Purchase value of the youngest regime. */
        public Amount $purchaseValue,
        /** GUID of the fixed asset group; null when the asset is in none. */
        public ?string $groupId,
        public int $version,
    ) {
        Guid::of($id);

        if ($code === '') {
            throw new InvalidArgumentException('Fixed asset code must not be empty.');
        }
    }
}
