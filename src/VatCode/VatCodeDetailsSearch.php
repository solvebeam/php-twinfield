<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Search on the VAT codes of the REST API, created by `vatCodes()->searchDetails()`.
 *
 * @extends RestSearch<VatCodeDetails>
 */
final readonly class VatCodeDetailsSearch extends RestSearch
{
    /**
     * Search pattern on code and name, e.g. `V*`.
     */
    public function pattern(string $pattern): self
    {
        return $this->with('searchPattern', $pattern);
    }

    public function type(VatCodeType $type): self
    {
        return $this->with('type', $type->value);
    }

    public function orderByCode(bool $ascending = true): self
    {
        return $this->sort('code', $ascending);
    }

    public function orderByName(bool $ascending = true): self
    {
        return $this->sort('name', $ascending);
    }

    public function orderByType(bool $ascending = true): self
    {
        return $this->sort('type', $ascending);
    }

    protected function map(JsonValue $item, CompanyReference $company): VatCodeDetails
    {
        return VatCodeDetailsReader::read($item, $company);
    }
}
