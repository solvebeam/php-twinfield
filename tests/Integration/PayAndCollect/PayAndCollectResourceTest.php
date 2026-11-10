<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\PayAndCollect;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live Pay & Collect queries (read-only). Creating, changing and deleting a run is in the write tests.
 */
final class PayAndCollectResourceTest extends IntegrationTestCase
{
    public function testListsTheRunsOfTheCompany(): void
    {
        // The test companies have no runs yet: Twinfield returns an empty `<GetRunsResult/>`.
        $runs = $this->twinfield->payAndCollect()->list();

        if ($runs === []) {
            self::addToAssertionCount(1);
        }

        foreach ($runs as $run) {
            self::assertSame($this->twinfield->companyCode(), $run->company->code);

            $info = $this->twinfield->payAndCollect()->get($run->payType, $run->id);

            self::assertSame($run->id, $info->id);
            self::assertSame($run->runType, $info->runType);
            self::assertSame($run->version->touched, $info->version->touched);
        }
    }

    public function testPayTypesAreInTheFinder(): void
    {
        $codes = array_column($this->twinfield->finder(FinderType::PaymentTypes)->get()->rows, 0);

        self::assertContains('SEPANLCT', $codes);
        self::assertContains('SEPANLDD', $codes);
    }
}
