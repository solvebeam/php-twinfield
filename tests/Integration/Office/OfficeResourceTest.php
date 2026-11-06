<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Office;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

final class OfficeResourceTest extends IntegrationTestCase
{
    public function testListsAllOfficesOverMultiplePages(): void
    {
        $offices = $this->twinfield->offices()->list();
        $total = $this->twinfield->finder(FinderType::Offices)->limit(1)->get()->totalRows;

        self::assertCount($total, $offices, 'All pages are fetched.');
        $codes = array_map(static fn (Office $office): string => $office->code, $offices);

        self::assertContains($this->environment->config->company(), $codes);
    }
}
