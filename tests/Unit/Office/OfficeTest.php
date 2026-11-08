<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Office;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SolveBeam\Twinfield\Office\DebitCredit;
use SolveBeam\Twinfield\Office\IntercompanyRelation;
use SolveBeam\Twinfield\Office\IntercompanyStatus;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Office\OfficeAddress;
use SolveBeam\Twinfield\Office\OfficeField;
use SolveBeam\Twinfield\Office\OfficeStatus;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Office\OfficeType;
use SolveBeam\Twinfield\Office\PaymentDiscount;
use SolveBeam\Twinfield\Office\SortField;
use SolveBeam\Twinfield\Office\SortOrder;
use SolveBeam\Twinfield\Office\SystemDimension;
use SolveBeam\Twinfield\Office\VatContact;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\UnexpectedResponseException;
use SolveBeam\Twinfield\Soap\SoapResponse;

final class OfficeTest extends TestCase
{
    public function testReadsAnOffice(): void
    {
        $office = self::fixture('office-read');

        self::assertSame('1000', $office->code);
        self::assertSame('Example Holding B.V.', $office->name);
        self::assertSame('Holding', $office->shortName);
        self::assertSame(OfficeStatus::Active, $office->status);
        self::assertEquals(new DateTimeImmutable('2014-09-15 21:20:45'), $office->created);
        self::assertEquals(new DateTimeImmutable('2026-02-03 14:38:46'), $office->modified);
        self::assertSame(29, $office->touched);
        self::assertEquals(new NamedReference('JANE', 'Jane Example', 'Jane'), $office->user);
        self::assertSame([OfficeField::DeclarationTimeframe, OfficeField::StartingQuarter], $office->lockedFields);
        self::assertTrue($office->isLocked(OfficeField::StartingQuarter));
        self::assertFalse($office->isLocked(OfficeField::Country));
    }

    public function testReadsTheGeneralSettings(): void
    {
        $general = self::fixture('office-read')->general;

        self::assertEquals(new NamedReference('EUR', 'Euro', 'Euro'), $general->baseCurrency);
        self::assertEquals(new NamedReference('EUR', 'Euro', 'Euro'), $general->reportingCurrency);
        self::assertSame(OfficeType::AccountantInternal, $general->type);
        self::assertFalse($general->demo);
        self::assertSame('NL001234567B01', $general->vatNumber);
        self::assertSame('NL001234568B02', $general->soleTraderVatNumber);
        self::assertSame('12345678', $general->cocNumber);
        self::assertNull($general->currentYear);
        self::assertNull($general->currentPeriod);
        self::assertNull($general->numberOfPeriods);
        self::assertSame(2025, $general->lastClosedYear);
        self::assertSame('NL00ZZZ123456780000', $general->creditorIdentifier);
        self::assertEquals(new NamedReference('BNK', 'Bank', 'Bank'), $general->defaultBank);
        self::assertEquals(new NamedReference('070', 'Match suspense accounts', '070'), $general->defaultMatching);
        self::assertSame('NORTH', $general->region);
        self::assertSame('STANDARD', $general->hierarchy);
        self::assertFalse($general->template);
        self::assertNull($general->templateOffice);
        self::assertTrue($general->editDimensionName);
        self::assertFalse($general->allowMultipleControlAccounts);
        self::assertSame(PaymentDiscount::VatExclusive, $general->paymentDiscount);
        self::assertSame('invoice', $general->scheme);
        self::assertTrue($general->allowIcMt940);
        self::assertFalse($general->lockWordInvoice);
        self::assertNull($general->sicMajorGroup);
        self::assertEquals(new OfficeAddress(
            field1: 'Main street 1',
            field2: 'Building A',
            field3: null,
            field4: null,
            field5: null,
            field6: null,
            postcode: '1234 AB',
            city: 'Utrecht',
            country: new NamedReference('NL', 'Nederland'),
            telephone: '030-1234567',
            fax: null,
        ), $general->address);
    }

    public function testReadsTheSystemDimensions(): void
    {
        $office = self::fixture('office-read');
        $dimensions = $office->systemDimensions;

        self::assertEquals(new SystemDimension('1300', 'Trade debtors', true), $dimensions->accountsReceivable);
        self::assertEquals(new SystemDimension('1600', 'Trade creditors', true), $dimensions->accountsPayable);
        self::assertEquals(new SystemDimension('1700', 'Suspense account', true), $dimensions->suspenseAccount);
        self::assertNull($dimensions->workInProgress);
        self::assertNull($dimensions->profitRiseProjects);
        self::assertEquals(new SystemDimension('8000', 'Turnover', false), $dimensions->turnover);
        self::assertEquals(new DebitCredit(
            new SystemDimension('4608', 'Exchange differences', false),
            new SystemDimension('4608', 'Exchange differences', false),
        ), $dimensions->exchangeDifference);
        self::assertSame('4607', $dimensions->paymentDifference->debit?->code);
        self::assertEquals(new DebitCredit(null, null), $dimensions->discount);
        self::assertNull($dimensions->teqCostCenter);
        self::assertEquals(new SystemDimension('00000', 'General', true), $dimensions->assetCostCenter);
        self::assertSame('0850', $dimensions->openingBalance?->code);
        self::assertSame('9999', $dimensions->closeProfitAndLoss?->code);
        self::assertNull($dimensions->forBalYnd);

        $types = $office->systemDimensionTypes;

        self::assertEquals(new NamedReference('DEB', 'Customers', 'Customers'), $types->accountsReceivable);
        self::assertSame(
            ['DEB', 'CRD', 'BAS', 'PNL', 'AST', 'PRJ', 'KPL', 'ACT'],
            [
                $types->accountsReceivable->code,
                $types->accountsPayable->code,
                $types->balance->code,
                $types->profitAndLoss->code,
                $types->fixedAssets->code,
                $types->projects->code,
                $types->costCenters->code,
                $types->activities->code,
            ],
        );
        self::assertSame('Profit & loss', $types->profitAndLoss->name);
    }

    public function testReadsTheCreditManagementSettings(): void
    {
        $creditManagement = self::fixture('office-read')->creditManagement;

        self::assertSame(14, $creditManagement->daysAfterDueDate);
        self::assertSame('500.00', $creditManagement->daysAfterDueDateAmount->value);
        self::assertSame(7, $creditManagement->daysAfterPartPayment);
        self::assertEquals(
            new NamedReference('160', 'Credit management call list', 'Call list'),
            $creditManagement->invoiceBrowse,
        );
        self::assertSame('161', $creditManagement->invoiceExplodeBrowse->code);
        // In the order of the ids; the empty third prompt is left out.
        self::assertSame([1 => 'fin.trs.line.dim2', 2 => 'fin.trs.line.invnumber'], $creditManagement->prompts);
        self::assertEquals([
            new SortField('fin.trs.line.datedue', SortOrder::Ascending),
            new SortField('fin.trs.line.dim2', SortOrder::Ascending),
            new SortField('fin.trs.line.invnumber', SortOrder::Descending),
        ], $creditManagement->sort);
    }

    public function testReadsTheVatSettings(): void
    {
        $vat = self::fixture('office-read')->vatManagement;

        self::assertEquals(new VatContact('001234567B01', '001234568B02', 'J. Example', '030-1234567'), $vat->taxpayer);
        self::assertEquals(
            new VatContact('123456', null, 'Example Accountants B.V.', '030-7654321'),
            $vat->intermediary,
        );
        self::assertEquals(new VatContact(null, null, null, null), $vat->seb);
        self::assertEquals(new NamedReference('013', 'Fiscal unity Example'), $vat->taxGroup);
        self::assertSame(1, $vat->declarationTimeframe);
        self::assertSame(21, $vat->startingQuarter);
        self::assertSame(1, $vat->icpTimeframe);
        self::assertFalse($vat->mayEstimate);
        self::assertTrue($vat->includeProvisionalPurchaseOrSalesTransactions);
        self::assertFalse($vat->includeProvisionalCashOrBankTransactions);
        self::assertFalse($vat->includeProvisionalJournalTransactions);
        self::assertFalse($vat->vatRollover);
        self::assertSame(1, $vat->fiscalYearTimeframeStart);
        self::assertSame('DIGIPOORT', $vat->defaultGatewayType);
        self::assertSame('PKI_EXAMPLE', $vat->defaultGatewayCode);
        self::assertSame('invoicebased', $vat->accountingScheme);
    }

    public function testReadsTheFixedAssetIntercompanyAndRegionalSettings(): void
    {
        $office = self::fixture('office-read');

        self::assertEquals(
            new NamedReference('ACTIVA', 'Depreciation', 'Depreciation'),
            $office->fixedAssets->transaction,
        );
        self::assertSame('310', $office->fixedAssets->browsePurchase->code);
        self::assertSame('310', $office->fixedAssets->browseDepreciate->code);
        self::assertSame('310', $office->fixedAssets->browseReconcile);

        self::assertSame(IntercompanyStatus::Both, $office->intercompany->status);
        self::assertEquals([
            new IntercompanyRelation(
                office: new NamedReference('1001', 'Example Retail B.V.', 'Retail'),
                transaction: null,
                dimension1: new DimensionReference('1320', 'BAS', 'Receivable from group companies'),
                dimension2: null,
                dimension3: null,
                description: 'Current account',
                indirect: false,
                indirectOffice: null,
            ),
        ], $office->intercompany->outgoing);
        self::assertEquals([
            new IntercompanyRelation(
                // No name: the user has no access to that office.
                office: new NamedReference('1002'),
                transaction: new NamedReference('RC', 'Current account', 'RC'),
                dimension1: new DimensionReference('1640', 'BAS', 'Payable to group companies'),
                dimension2: null,
                dimension3: null,
                description: null,
                indirect: false,
                indirectOffice: null,
            ),
        ], $office->intercompany->incoming);

        self::assertSame('dd/mm/yyyy', $office->regional->dateFormat);
        self::assertSame('.', $office->regional->thousandsSeparator);
        self::assertSame(',', $office->regional->decimalSeparator);
    }

    public function testReadsAnOfficeWithEmptySettings(): void
    {
        $office = self::fixture('office-read-minimal');

        self::assertNull($office->shortName);
        self::assertNull($office->general->reportingCurrency);
        self::assertSame(OfficeType::AccountantExternal, $office->general->type);
        self::assertTrue($office->general->demo);
        self::assertNull($office->general->vatNumber);
        self::assertNull($office->general->cocNumber);
        self::assertNull($office->general->lastClosedYear);
        // A bank book code without name: the bank book does not exist (anymore).
        self::assertEquals(new NamedReference('RABOBANK'), $office->general->defaultBank);
        self::assertNull($office->general->region);
        self::assertNull($office->general->hierarchy);
        self::assertEquals(
            new OfficeAddress(
                field1: null,
                field2: null,
                field3: null,
                field4: null,
                field5: null,
                field6: null,
                postcode: null,
                city: null,
                country: new NamedReference('NL', 'Nederland'),
                telephone: null,
                fax: null,
            ),
            $office->general->address,
        );
        self::assertNull($office->systemDimensions->turnover);
        self::assertNull($office->systemDimensions->assetCostCenter);
        self::assertCount(2, $office->creditManagement->sort);
        self::assertNull($office->vatManagement->taxGroup);
        self::assertSame(0, $office->vatManagement->declarationTimeframe);
        self::assertSame(30, $office->vatManagement->startingQuarter);
        self::assertTrue($office->vatManagement->mayEstimate);
        self::assertFalse($office->vatManagement->includeProvisionalPurchaseOrSalesTransactions);
        self::assertTrue($office->vatManagement->vatRollover);
        self::assertNull($office->vatManagement->defaultGatewayType);
        self::assertSame(IntercompanyStatus::None, $office->intercompany->status);
        self::assertSame([], $office->intercompany->outgoing);
        self::assertSame([], $office->intercompany->incoming);
        self::assertSame(
            [OfficeField::Template, OfficeField::Country, OfficeField::VatRollover],
            $office->lockedFields,
        );
    }

    public function testThrowsOnAnUnknownOfficeType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'office/general/type is "sme", expected one of accountantinternal, accountantexternal.',
        );

        self::fixture('office-read-unknown-type');
    }

    public function testThrowsOnALowercaseBooleanWhereTwinfieldCapitalises(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'office/vatmanagement/includepurchaseorsalesprovisionaltransactions is "true", expected True or False.',
        );

        self::fixture('office-read-invalid-boolean');
    }

    public function testSummaryRejectsAnEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OfficeSummary('', 'Example Holding B.V.', '0f8fad5b-d9cb-469f-a165-70867728950e', 'NL', 1, 21);
    }

    public function testReferenceRejectsAnEmptyCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NamedReference('');
    }

    private static function fixture(string $name): Office
    {
        $http = ReplayClient::response($name);
        $result = ProcessXmlResult::fromSoapResponse(SoapResponse::fromHttpResponse($http));

        return Office::fromXml(XmlElement::root($result));
    }
}
