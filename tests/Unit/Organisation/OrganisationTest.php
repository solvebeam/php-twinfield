<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Organisation;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;

final class OrganisationTest extends TestCase
{
    public function testComparesOrganisationsOnTheirId(): void
    {
        $organisation = new Organisation('0b6a5f6e-1111-2222-3333-444455556666', 'EXAMPLE');

        self::assertTrue($organisation->equals(new Organisation('0B6A5F6E-1111-2222-3333-444455556666', 'RENAMED')));
        self::assertFalse($organisation->equals(new Organisation('7c9e6679-7425-40de-944b-e07fc1f90ae7', 'EXAMPLE')));
    }

    public function testRefusesAnEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Organisation('', 'EXAMPLE');
    }

    public function testRefusesAnEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Organisation('0b6a5f6e-1111-2222-3333-444455556666', '');
    }

    public function testTellsCompaniesWithTheSameCodeInOtherOrganisationsApart(): void
    {
        $example = new Organisation('0b6a5f6e-1111-2222-3333-444455556666', 'EXAMPLE');
        $other = new Organisation('7c9e6679-7425-40de-944b-e07fc1f90ae7', 'OTHER');

        $company = new CompanyReference($example, '1000');

        self::assertTrue($company->equals(new CompanyReference($example, '1000')));
        self::assertFalse($company->equals(new CompanyReference($example, '1001')));
        self::assertFalse($company->equals(new CompanyReference($other, '1000')));
    }

    public function testRefusesAnEmptyCompanyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CompanyReference(new Organisation('0b6a5f6e-1111-2222-3333-444455556666', 'EXAMPLE'), '');
    }
}
