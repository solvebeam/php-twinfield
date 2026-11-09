<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Office;

use League\OAuth2\Client\Token\AccessToken;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Office\OfficeStatus;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

final class OfficeResourceTest extends IntegrationTestCase
{
    public function testListsAllOfficesOverMultiplePages(): void
    {
        $offices = $this->twinfield->offices()->list();
        $total = $this->twinfield->finder(FinderType::Offices)->limit(1)->get()->totalRows;

        self::assertCount($total, $offices, 'All pages are fetched.');
        $codes = array_map(static fn (OfficeSummary $office): string => $office->code, $offices);

        self::assertContains($this->environment->config->company(), $codes);
        self::assertSame(count($codes), count(array_unique($codes)), 'Every office is listed once.');
    }

    public function testOfficesBelongToTheOrganisationOfTheAccessToken(): void
    {
        $validation = $this->environment->session->provider()->getAccessTokenValidation(
            new AccessToken(['access_token' => $this->environment->session->accessToken()]),
        );
        $organisation = $this->twinfield->organisation();

        self::assertEquals($validation->organisation, $organisation);

        foreach ($this->twinfield->offices()->list() as $office) {
            self::assertTrue($office->organisation->equals($organisation));
        }

        $office = $this->twinfield->offices()->get($this->environment->config->company());

        self::assertTrue($office->reference()->equals($this->twinfield->companyReference()));
    }

    public function testSearchesWithoutTaxGroup(): void
    {
        $offices = $this->twinfield->offices();

        self::assertLessThanOrEqual($offices->search()->count(), $offices->search()->withoutTaxGroup()->count());
    }

    public function testSearchesByCode(): void
    {
        $company = $this->environment->config->company();

        $page = $this->twinfield->offices()->search($company)->inCode()->get();

        self::assertSame([$company], array_map(
            static fn (OfficeSummary $office): string => $office->code,
            iterator_to_array($page),
        ));
    }

    public function testReadsTheCurrentOffice(): void
    {
        $office = $this->twinfield->offices()->current();

        self::assertSame($this->environment->config->company(), $office->code);
        self::assertSame(OfficeStatus::Active, $office->status);
        self::assertSame('DEB', $office->systemDimensionTypes->accountsReceivable->code);
    }

    public function testReadsAnOfficeWithoutCompany(): void
    {
        $company = $this->environment->config->company();

        self::assertSame($company, $this->twinfield->withoutCompany()->offices()->get($company)->code);
    }

    public function testReadsTheFirstOffices(): void
    {
        // Every office must parse; a few keep the test fast and within the rate limit.
        foreach (array_slice($this->twinfield->offices()->list(), 0, 5) as $summary) {
            $office = $this->twinfield->offices()->get($summary->code);

            self::assertSame($summary->code, $office->code);
            self::assertSame($summary->name, $office->name);
            self::assertSame($summary->vatPeriod, $office->vatManagement->declarationTimeframe);
            self::assertSame($summary->startingQuarter, $office->vatManagement->startingQuarter);
        }
    }

    public function testFindsNoUnknownOffice(): void
    {
        self::assertNull($this->twinfield->offices()->find('NO_SUCH_OFFICE'));
        self::assertNull($this->twinfield->withoutCompany()->offices()->find('NO_SUCH_OFFICE'));
    }
}
