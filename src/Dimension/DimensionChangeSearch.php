<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Rest\JsonValue;
use SolveBeam\Twinfield\Rest\RestSearch;

/**
 * Customers or suppliers changed since a moment, created by `customers()->changes($since)` or
 * `suppliers()->changes($since)`.
 *
 * @extends RestSearch<DimensionChange>
 */
final readonly class DimensionChangeSearch extends RestSearch
{
    protected function map(JsonValue $item, CompanyReference $company): DimensionChange
    {
        return new DimensionChange(
            company: $company,
            id: $item->uuid('id'),
            name: $item->string('name'),
            status: $item->enum('status', DimensionChangeStatus::class),
            modified: $item->timestamp('modified'),
        );
    }
}
