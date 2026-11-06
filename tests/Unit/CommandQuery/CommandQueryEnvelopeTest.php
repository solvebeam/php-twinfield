<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CommandQuery;

use DateTimeImmutable;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\CommandQueryEnvelope;
use SolveBeam\Twinfield\CommandQuery\Nil;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;

/**
 * Compares with the raw envelopes on https://developers.twinfield.com/documentation/api/miscellaneous/periods.
 */
final class CommandQueryEnvelopeTest extends TestCase
{
    public function testBuildsQueryEnvelope(): void
    {
        $envelope = CommandQueryEnvelope::query('PeriodService', new Query('GetPeriods', ['Year' => 2021]), $this->authentication());

        self::assertXmlStringEqualsXmlString(<<<'XML'
            <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
              <s:Header>
                <Authentication xmlns="http://www.twinfield.com/" xmlns:h="http://schemas.datacontract.org/2004/07/Twinfield.WebServices.Shared">
                  <h:AccessToken>token-123</h:AccessToken>
                  <h:CompanyCode>1000</h:CompanyCode>
                </Authentication>
              </s:Header>
              <s:Body>
                <Query xmlns="http://www.twinfield.com/" xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:a="http://schemas.datacontract.org/2004/07/Twinfield.WebServices.PeriodService" i:type="a:GetPeriods">
                  <a:Year>2021</a:Year>
                </Query>
              </s:Body>
            </s:Envelope>
            XML, $envelope->toXml());
    }

    public function testBuildsCommandEnvelopeWithFieldsInGivenOrder(): void
    {
        $command = new Command('ChangeEndDate', [
            'EndDate' => new DateTimeImmutable('2021-02-13 00:00:00'),
            'Name' => null,
            'PeriodNumber' => 2,
            'Year' => 2021,
        ]);

        $xml = CommandQueryEnvelope::command('PeriodService', $command, $this->authentication())->toXml();

        self::assertStringContainsString('<Command xmlns="http://www.twinfield.com/"', $xml);
        self::assertStringContainsString('i:type="a:ChangeEndDate"', $xml);
        self::assertMatchesRegularExpression(
            '~<a:EndDate>2021-02-13T00:00:00</a:EndDate>\s*<a:PeriodNumber>2</a:PeriodNumber>\s*<a:Year>2021</a:Year>~',
            $xml,
            'Fields keep the given (WSDL) order; null fields are left out.',
        );
    }

    public function testFormatsBooleansAndEscapesText(): void
    {
        $query = new Query('GetBudgetByProfitAndLoss', ['Code' => 'A&B <1>', 'IncludeFinal' => true, 'IncludeProvisional' => false]);

        $xml = CommandQueryEnvelope::query('BudgetService', $query, $this->authentication())->toXml();

        self::assertStringContainsString('xmlns:a="http://schemas.datacontract.org/2004/07/Twinfield.WebServices.BudgetService"', $xml);
        self::assertStringContainsString('<a:Code>A&amp;B &lt;1&gt;</a:Code>', $xml);
        self::assertStringContainsString('<a:IncludeFinal>true</a:IncludeFinal>', $xml);
        self::assertStringContainsString('<a:IncludeProvisional>false</a:IncludeProvisional>', $xml);
    }

    public function testBuildsNestedElementsAndNilFields(): void
    {
        $command = new Command('ChangeBankBookBankAccount', [
            'Code' => 'ING',
            'BankAccount' => [
                'Bic' => Nil::Value,
                'Country' => null,
                'Iban' => 'NL45INGB0003255112',
            ],
        ]);

        $xml = CommandQueryEnvelope::command('BankBookService', $command, $this->authentication())->toXml();

        self::assertMatchesRegularExpression(
            '~<a:Code>ING</a:Code>\\s*<a:BankAccount>\\s*<a:Bic i:nil="true"/>\\s*<a:Iban>NL45INGB0003255112</a:Iban>\\s*</a:BankAccount>~',
            $xml,
            'Arrays become complex elements in the service namespace; Nil is i:nil, null is left out.',
        );
    }

    public function testRefusesUnsupportedFieldValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Command & Query field "Amount" has an unsupported type: float.');

        CommandQueryEnvelope::command('BudgetService', new Command('Change', ['Amount' => 1.5]), $this->authentication())->toXml();
    }

    public function testSetsSoapActionPerKind(): void
    {
        $factory = new Psr17SoapRequestFactory(new Psr17Factory(), new Psr17Factory());
        $url = 'https://example.test/webservices/PeriodService.svc';

        $query = CommandQueryEnvelope::query('PeriodService', new Query('GetYears'), $this->authentication())->toRequest($url, $factory);
        $command = CommandQueryEnvelope::command('PeriodService', new Command('DeleteYear', ['Year' => 2021]), $this->authentication())->toRequest($url, $factory);

        self::assertSame('"http://www.twinfield.com/PeriodService/Query"', $query->getHeaderLine('SOAPAction'));
        self::assertSame('"http://www.twinfield.com/PeriodService/Process"', $command->getHeaderLine('SOAPAction'));
        self::assertSame('text/xml; charset=utf-8', $query->getHeaderLine('Content-Type'));
        self::assertSame($url, (string) $query->getUri());
    }

    private function authentication(): AuthenticationHeader
    {
        return new AuthenticationHeader('token-123', '1000');
    }
}
