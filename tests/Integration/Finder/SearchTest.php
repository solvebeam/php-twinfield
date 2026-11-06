<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Finder;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live Finder searches (read-only) against TWINFIELD_COMPANY.
 */
final class SearchTest extends IntegrationTestCase
{
    public function testSearchesOffices(): void
    {
        $result = $this->twinfield->finder(FinderType::Offices)->get();

        self::assertGreaterThan(0, $result->totalRows);
        self::assertNotEmpty($result->columns);

        $codes = array_column($result->rows, 0);

        self::assertContains($this->environment->config->company(), $codes, 'The test company is one of the offices.');
    }

    public function testSearchesDimensions(): void
    {
        $result = $this->twinfield->finder(FinderType::Dimensions)->limit(5)->get();

        self::assertGreaterThan(0, $result->totalRows);
        self::assertLessThanOrEqual(5, count($result));

        foreach ($result as $row) {
            self::assertCount(count($result->columns), $row, 'Each row has a value per column.');
        }
    }
}
