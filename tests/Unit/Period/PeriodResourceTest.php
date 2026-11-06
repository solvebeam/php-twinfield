<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Period;

use DateTimeImmutable;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Period\PeriodServiceFaultCode;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class PeriodResourceTest extends TestCase
{
    public function testListsYears(): void
    {
        $http = ReplayClient::fixture('period-years');

        self::assertSame([2023, 2024, 2025], $this->twinfield($http)->periods()->years());

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/PeriodService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/PeriodService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('i:type="a:GetYears"', (string) $request->getBody());
    }

    public function testListsPeriodsOfAYear(): void
    {
        $http = ReplayClient::fixture('period-periods');

        $periods = $this->twinfield($http)->periods()->list(2025);

        self::assertEquals([
            new Period(0, 'Openingsbalans 2025', null, true),
            new Period(1, 'januari 2025', new DateTimeImmutable('2025-01-31T00:00:00'), false),
            new Period(2, 'februari 2025', new DateTimeImmutable('2025-02-28T00:00:00'), true),
            new Period(55, 'Voorafgaande journaalposten 2025', null, true),
        ], $periods);

        self::assertStringContainsString('<a:Year>2025</a:Year>', (string) $http->lastRequest()->getBody());
    }

    public function testThrowsWhenAPeriodHasNoName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GetPeriodsResult/Periods/Period[1]/Name is missing.');

        $this->twinfield(ReplayClient::fixture('period-periods-missing-name'))->periods()->list(2025);
    }

    public function testThrowsOnUnexpectedResultType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected query result of type "GetYearsResult", received "b:GetPeriodsResult".');

        $this->twinfield(ReplayClient::fixture('period-periods'))->periods()->years();
    }

    public function testThrowsServiceFaultWithCode(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('period-fault-year-not-found', 500))->periods()->list(1901);
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('s:Client: Year 1901 bestaat niet. (PeriodServiceFault, code YearNotFound)', $exception->getMessage());
            self::assertSame('PeriodServiceFault', $exception->type);
            self::assertSame(PeriodServiceFaultCode::YearNotFound, PeriodServiceFaultCode::tryFrom($exception->errorCode));
            self::assertSame('Year 1901 bestaat niet.', $exception->errorMessage);
        }
    }

    public function testSendsCommandsWithFieldsInWsdlOrder(): void
    {
        $http = new ReplayClient(...array_fill(0, 7, ReplayClient::response('period-process')));
        $periods = $this->twinfield($http)->periods();

        $periods->createYear(12);
        $periods->changeEndDate(2021, 2, new DateTimeImmutable('2021-02-13'));
        $periods->changeName(2021, 2, 'February 2021');
        $periods->open(2021, 2);
        $periods->close(2021, 2);
        $periods->resetYears(2020, 12);
        $periods->deleteYear(2021);

        $bodies = array_map(static fn ($request): string => (string) preg_replace('~>\s+<~', '><', (string) $request->getBody()), $http->requests);

        self::assertStringContainsString('i:type="a:CreateYear"><a:NumberOfPeriods>12</a:NumberOfPeriods></Command>', $bodies[0]);
        self::assertStringContainsString('i:type="a:ChangeEndDate"><a:EndDate>2021-02-13T00:00:00</a:EndDate><a:PeriodNumber>2</a:PeriodNumber><a:Year>2021</a:Year></Command>', $bodies[1]);
        self::assertStringContainsString('i:type="a:ChangeName"><a:Name>February 2021</a:Name><a:PeriodNumber>2</a:PeriodNumber><a:Year>2021</a:Year></Command>', $bodies[2]);
        self::assertStringContainsString('i:type="a:OpenPeriod"><a:PeriodNumber>2</a:PeriodNumber><a:Year>2021</a:Year></Command>', $bodies[3]);
        self::assertStringContainsString('i:type="a:ClosePeriod"><a:PeriodNumber>2</a:PeriodNumber><a:Year>2021</a:Year></Command>', $bodies[4]);
        self::assertStringContainsString('i:type="a:ResetYears"><a:NewNumberOfPeriods>12</a:NewNumberOfPeriods><a:NewYear>2020</a:NewYear></Command>', $bodies[5]);
        self::assertStringContainsString('i:type="a:DeleteYear"><a:Year>2021</a:Year></Command>', $bodies[6]);

        foreach ($http->requests as $request) {
            self::assertSame('"http://www.twinfield.com/PeriodService/Process"', $request->getHeaderLine('SOAPAction'));
        }
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->periods()->years();
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(new StaticAccessTokenProvider('token-123', 'https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
