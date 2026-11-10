<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Finder;

use PHPUnit\Framework\Attributes\DataProvider;
use SolveBeam\Twinfield\Finder\FinderErrorCode;
use SolveBeam\Twinfield\Finder\FinderException;
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

    /**
     * A few types with other columns than code and name. All types were checked once with a
     * script (see the Finder section of the documentation differences); searching them all here
     * runs into the rate limit.
     *
     * @return iterable<string, array{FinderType, int}>
     */
    public static function types(): iterable
    {
        yield 'CTR' => [FinderType::Countries, 2];
        yield 'PRD' => [FinderType::Periods, 2];
        yield 'VGM' => [FinderType::VatGroupCountries, 3];
        yield 'VATN' => [FinderType::VatNumbers, 6];
    }

    #[DataProvider('types')]
    public function testSearchesType(FinderType $type, int $columns): void
    {
        $result = $this->twinfield->finder($type)->limit(3)->get();

        self::assertCount($columns, $result->columns);

        foreach ($result as $row) {
            self::assertCount(count($result->columns), $row, 'Each row has a value per column.');
        }
    }

    public function testSubItemsNeedAnArticle(): void
    {
        try {
            $this->twinfield->finder(FinderType::SubItems)->get();
            self::fail('Expected a FinderException.');
        } catch (FinderException $exception) {
            self::assertSame(FinderErrorCode::OptionMandatory, $exception->messages[0]->code);
            self::assertSame(['article'], $exception->messages[0]->parameters);
        }
    }

    public function testRefusesAnUnknownOption(): void
    {
        try {
            $this->twinfield->finder(FinderType::Dimensions)->option('foo', 'bar')->get();
            self::fail('Expected a FinderException.');
        } catch (FinderException $exception) {
            self::assertSame(FinderErrorCode::OptionNotAllowed, $exception->messages[0]->code);
            self::assertSame(['foo'], $exception->messages[0]->parameters);
        }
    }

    public function testRefusesASearchFieldOutOfRange(): void
    {
        try {
            $this->twinfield->finder(FinderType::Dimensions)->field(9)->get();
            self::fail('Expected a FinderException.');
        } catch (FinderException $exception) {
            self::assertSame(FinderErrorCode::ParameterOutOfRange, $exception->messages[0]->code);
            self::assertSame(['field', '0', '4'], $exception->messages[0]->parameters);
        }
    }

    public function testSearchesInAllCompanies(): void
    {
        $result = $this->twinfield->finder(FinderType::VatCodes)->allCompanies()->limit(5)->get();

        self::assertCount(4, $result->columns, 'Code, name, company code and company ID.');

        $page = $this->twinfield->vatCodes()->search()->inAllCompanies()->limit(50)->get();

        foreach ($page as $summary) {
            self::assertNotSame('*', $summary->company->code);
        }
    }
}
