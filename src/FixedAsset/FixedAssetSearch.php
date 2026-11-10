<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeInterface;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Search on the fixed assets of the new fixed assets module, created by `fixedAssets()->search()`.
 *
 * @extends RestSearch<FixedAssetSummary>
 */
final readonly class FixedAssetSearch extends RestSearch
{
    /**
     * Search pattern on code and description, e.g. `60*`.
     */
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    /**
     * Only assets with this status, e.g. FixedAssetStatus::Active; InactiveAnyReason for all inactive ones.
     */
    public function status(FixedAssetStatus $status): self
    {
        return $this->with('status', $status->value);
    }

    /**
     * The values (book value, …) in this regime.
     */
    public function regime(Regime $regime): self
    {
        return $this->with('regime', $regime->value);
    }

    public function inClass(string $classId): self
    {
        return $this->with('classId', Guid::of($classId, 'Class ID'));
    }

    public function inGroup(string $groupId): self
    {
        return $this->with('groupId', Guid::of($groupId, 'Group ID'));
    }

    public function atLocation(string $locationId): self
    {
        return $this->with('locationId', Guid::of($locationId, 'Location ID'));
    }

    public function ofType(string $typeId): self
    {
        return $this->with('typeId', Guid::of($typeId, 'Type ID'));
    }

    public function taxReason(string $taxReason): self
    {
        return $this->with('taxReason', $taxReason);
    }

    /**
     * Class, group and location as they were in this period (time-related filters).
     */
    public function asOf(Period $period): self
    {
        return $this->with('timeRelatedFiltersYear', $period->year)
            ->with('timeRelatedFiltersPeriodNumber', $period->number);
    }

    public function firstUsedBetween(?DateTimeInterface $from, ?DateTimeInterface $to): self
    {
        return $this->with('firstUseFrom', $from?->format('Y-m-d'))->with('firstUseTo', $to?->format('Y-m-d'));
    }

    public function investedBetween(?DateTimeInterface $from, ?DateTimeInterface $to): self
    {
        return $this->with('investmentFrom', $from?->format('Y-m-d'))->with('investmentTo', $to?->format('Y-m-d'));
    }

    /**
     * @param Amount|numeric-string|null $from
     * @param Amount|numeric-string|null $to
     */
    public function amountBetween(Amount|string|null $from, Amount|string|null $to): self
    {
        return $this->with('amountFrom', Amount::ofNullable($from)?->value)
            ->with('amountTo', Amount::ofNullable($to)?->value);
    }

    /**
     * Sort by a field of the summary, e.g. `code` or `description`.
     */
    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): FixedAssetSummary
    {
        return FixedAssetReader::summary($item, $company);
    }
}
