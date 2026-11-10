<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Budget;

use DateTimeInterface;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the spreading templates, created by `spreadingTemplates()->search()`.
 *
 * @extends RestSearch<SpreadingTemplate>
 */
final readonly class SpreadingTemplateSearch extends RestSearch
{
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function modifiedSince(DateTimeInterface $since): self
    {
        return $this->with('modifiedSince', $since->format(DATE_ATOM));
    }

    /**
     * Also read the weights of the periods.
     */
    public function withDistribution(): self
    {
        return $this->with('includeDistributionData', true);
    }

    public function orderBy(string $field, bool $ascending = true): self
    {
        return $this->sort($field, $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): SpreadingTemplate
    {
        return SpreadingTemplateResource::read($item, $company);
    }
}
