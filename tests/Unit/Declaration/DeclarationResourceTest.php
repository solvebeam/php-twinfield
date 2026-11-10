<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\Declaration;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SolveBeam\Twinfield\Declaration\DeclarationException;
use SolveBeam\Twinfield\Declaration\DeclarationLoadMessage;
use SolveBeam\Twinfield\Declaration\DeclarationMessageType;
use SolveBeam\Twinfield\Declaration\DeclarationStatusMessage;
use SolveBeam\Twinfield\Declaration\DeclarationStatusStep;
use SolveBeam\Twinfield\Declaration\DeclarationType;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Declarations (declarations.asmx). The `declarations-*` fixtures without a comment are recorded
 * and anonymized responses (the anonymizer also scrambles years and the period); `-list-empty`,
 * `-list-without-extra-information` and `-list-invalid-code` copy live responses with fictitious
 * values; `-list-invalid-*` and `-set-to-*` are fictitious, from the WSDL.
 */
final class DeclarationResourceTest extends TestCase
{
    public function testListsTheReturnsOfACompany(): void
    {
        $http = ReplayClient::fixture('declarations-list');
        $returns = $this->twinfield($http)->declarations()->list(year: 2025);

        self::assertCount(3, $returns);

        $return = $returns[0];

        self::assertSame(6679, $return->id);
        self::assertSame(DeclarationType::VatTurnover, $return->type);
        self::assertSame('Hzc-nnqwmgpg 5983 eoo', $return->name);
        self::assertSame(7039, $return->year);
        self::assertSame('phy', $return->period);
        self::assertSame(DeclarationStatusStep::Approved, $return->status->step);
        self::assertSame('Qsoenrchohn', $return->status->description);
        self::assertNull($return->status->extraInformation, 'An empty <ExtraInformation/>.');
        self::assertSame('NCEPA', $return->assignee->code);
        self::assertSame('Kbwyj Hpswgc', $return->assignee->name);
        self::assertSame('6443', $return->company->code, 'The company of the return, not of the client.');
        self::assertSame('EXAMPLE', $return->company->organisation->code);
        self::assertSame('THR Bnwhvehysa Uhaug', $return->companyName);

        self::assertSame('http://www.twinfield.com/GetRangeOfSummariesByDeclarationYear', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString(
            '<GetRangeOfSummariesByDeclarationYear><companyCode>1000</companyCode><declarationYear>2025</declarationYear>'
            . '<startIndex>0</startIndex><count>100</count></GetRangeOfSummariesByDeclarationYear>',
            $this->body($http),
        );
        self::assertStringContainsString('<CompanyCode>1000</CompanyCode>', $this->body($http), 'The company in the header.');
    }

    public function testListsAllReturnsOfACompany(): void
    {
        $http = ReplayClient::fixture('declarations-list-empty');

        self::assertSame([], $this->twinfield($http)->declarations()->all());
        self::assertStringContainsString('<GetAllSummaries><companyCode>1000</companyCode></GetAllSummaries>', $this->body($http));
    }

    public function testListsAPage(): void
    {
        $http = new ReplayClient(self::emptyList('GetRangeOfSummaries'));

        $this->twinfield($http)->declarations()->list(offset: 200, limit: 50);

        self::assertStringContainsString(
            '<GetRangeOfSummaries><companyCode>1000</companyCode><startIndex>200</startIndex><count>50</count></GetRangeOfSummaries>',
            $this->body($http),
        );
    }

    public function testListsReturnsModifiedSinceWithTheTimeZone(): void
    {
        $http = new ReplayClient(self::emptyList('GetRangeOfSummariesSinceModified'));
        $since = new DateTimeImmutable('2026-11-06 11:07:46', new DateTimeZone('Europe/Amsterdam'));

        $this->twinfield($http)->declarations()->list(modifiedSince: $since);

        self::assertStringContainsString(
            '<GetRangeOfSummariesSinceModified><companyCode>1000</companyCode><startIndex>0</startIndex><count>100</count>'
            . '<modifiedSince>2026-11-06T11:07:46+01:00</modifiedSince></GetRangeOfSummariesSinceModified>',
            $this->body($http),
        );
    }

    public function testListsReturnsOfAYearModifiedSince(): void
    {
        $http = new ReplayClient(self::emptyList('GetRangeOfSummariesByDeclarationYearAndSinceModified'));
        $since = new DateTimeImmutable('2026-10-01T00:00:00Z');

        $this->twinfield($http)->declarations()->list(year: 2026, modifiedSince: $since);

        self::assertStringContainsString(
            '<GetRangeOfSummariesByDeclarationYearAndSinceModified><companyCode>1000</companyCode>'
            . '<declarationYear>2026</declarationYear><startIndex>0</startIndex><count>100</count>'
            . '<modifiedSince>2026-10-01T00:00:00+00:00</modifiedSince></GetRangeOfSummariesByDeclarationYearAndSinceModified>',
            $this->body($http),
        );
    }

    public function testListsReturnsOfTheOrganisation(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('declarations-list-empty'),
            self::emptyList('GetRangeOfSummariesByDeclarationYear'),
        );
        $declarations = $this->twinfield($http)->declarations();

        $declarations->allForOrganisation();
        self::assertStringContainsString('<GetAllSummaries><companyCode>*</companyCode></GetAllSummaries>', $this->body($http));
        self::assertStringContainsString('<CompanyCode>1000</CompanyCode>', $this->body($http));

        $declarations->listForOrganisation(year: 2026);
        self::assertStringContainsString('<GetRangeOfSummariesByDeclarationYear><companyCode>*</companyCode>', $this->body($http));
    }

    public function testReadsAReturnWithoutExtraInformation(): void
    {
        $returns = $this->twinfield(ReplayClient::fixture('declarations-list-without-extra-information'))->declarations()->all();

        self::assertNull($returns[0]->status->extraInformation);
    }

    public function testThrowsOnAnUnknownStatusStep(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetAllSummariesResponse/vatReturn/DeclarationSummary/Status/StepIndex is "9", expected a status step from 1 to 8.',
        );

        $this->twinfield(ReplayClient::fixture('declarations-list-invalid-step'))->declarations()->all();
    }

    public function testThrowsOnAnUnknownType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('DocumentCode is "VATOTHER"');

        $this->twinfield(ReplayClient::fixture('declarations-list-invalid-type'))->declarations()->all();
    }

    public function testThrowsTheErrorMessages(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('declarations-list-invalid-code'))->declarations()->all();
            self::fail('Expected a DeclarationException.');
        } catch (DeclarationException $exception) {
            self::assertSame('GetAllSummaries: Deze code bestaat niet. (InvalidCode)', $exception->getMessage());
            self::assertSame('GetAllSummaries', $exception->operation);
            self::assertCount(1, $exception->messages);
            self::assertSame(DeclarationMessageType::Error, $exception->messages[0]->type);
            self::assertSame(DeclarationLoadMessage::InvalidCode, $exception->messages[0]->code);
            self::assertSame([null], $exception->messages[0]->parameters, 'A nil parameter.');
        }
    }

    public function testCountsTheReturns(): void
    {
        $http = ReplayClient::fixture('declarations-count');

        self::assertSame(9, $this->twinfield($http)->declarations()->count());
        self::assertStringContainsString('<GetNumberOfDeclarations><companyCode>1000</companyCode></GetNumberOfDeclarations>', $this->body($http));
    }

    public function testDownloadsAVatReturnAsXbrl(): void
    {
        $http = ReplayClient::fixture('declarations-vat-return-xbrl');
        $xml = $this->twinfield($http)->declarations()->vatReturnXbrl(2909, messageId: true);
        $document = self::document($xml);

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertSame('xbrli:xbrl', $document->documentElement?->tagName);
        self::assertSame('http://www.xbrl.org/2003/instance', $document->documentElement->namespaceURI);
        self::assertStringNotContainsString('twinfield.com', $xml, 'Only the return, not the SOAP response.');
        self::assertStringContainsString(
            '<GetVatReturnAsXbrl><documentId>2909</documentId><isMessageIdRequired>true</isMessageIdRequired></GetVatReturnAsXbrl>',
            $this->body($http),
        );
    }

    public function testDownloadsAVatReturnAsXmlWithoutNamespace(): void
    {
        $xml = $this->twinfield(ReplayClient::fixture('declarations-vat-return-xml'))->declarations()->vatReturnXml(2909);
        $document = self::document($xml);

        self::assertSame('AangifteOmzetbelasting', $document->documentElement?->localName);
        self::assertNull($document->documentElement->namespaceURI, 'Not in the namespace of the SOAP response.');
        self::assertSame('361008686O48', $document->getElementsByTagName('OBNummer')->item(0)?->textContent);
    }

    public function testDownloadsAnIctReturn(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('declarations-ict-return-xbrl'),
            ReplayClient::response('declarations-ict-return-xml'),
        );
        $declarations = $this->twinfield($http)->declarations();

        self::assertSame('xbrl', self::document($declarations->ictReturnXbrl(11926))->documentElement?->localName);
        self::assertStringContainsString('<isMessageIdRequired>false</isMessageIdRequired>', $this->body($http));

        $xml = self::document($declarations->ictReturnXml(11926));

        self::assertSame('OpgaafICP', $xml->documentElement?->localName);
        self::assertSame('http://xml.belastingdienst.nl/schemas/ICP/2010/01', $xml->documentElement->namespaceURI);
    }

    public function testDownloadsATaxGroupReturn(): void
    {
        $http = ReplayClient::fixture('declarations-tax-group-return-xbrl');

        $xml = $this->twinfield($http)->declarations()->taxGroupVatReturnXbrl(11934);

        self::assertSame('xbrl', self::document($xml)->documentElement?->localName);
        self::assertSame('http://www.twinfield.com/GetTaxGroupVatReturnAsXbrl', $http->lastRequest()->getHeaderLine('SOAPAction'));
    }

    public function testGivesThePaymentReference(): void
    {
        $http = ReplayClient::fixture('declarations-vat-return-payment-reference');

        self::assertSame('9096427548239821+', $this->twinfield($http)->declarations()->vatReturnPaymentReference(2909));
        self::assertStringContainsString('<GetVatReturnPaymentReference><documentId>2909</documentId></GetVatReturnPaymentReference>', $this->body($http));
    }

    public function testGivesNoPaymentReferenceForAnEmptyElement(): void
    {
        $http = ReplayClient::fixture('declarations-payment-reference-empty');

        self::assertNull($this->twinfield($http)->declarations()->vatReturnPaymentReference(10838));
    }

    public function testThrowsWhenTheTypeHasNoPaymentReference(): void
    {
        $this->expectException(DeclarationException::class);
        $this->expectExceptionMessage('(InvalidDocumentType)');

        $this->twinfield(ReplayClient::fixture('declarations-payment-reference-invalid-document-type'))
            ->declarations()
            ->vatReturnPaymentReference(11926);
    }

    public function testThrowsASoapFaultForAnUnknownDocument(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('declarations-unknown-document', 500))->declarations()->vatReturnXbrl(999999);
            self::fail('Expected a ClassicServiceFaultException.');
        } catch (ClassicServiceFaultException $exception) {
            self::assertSame('100', $exception->errorCode);
            self::assertSame('GetDeclarationXbrl', $exception->source);
        }
    }

    public function testThrowsWhenTheResponseIsForAnotherOperation(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected <GetVatReturnAsXmlResponse>, got <GetNumberOfDeclarationsResponse>.');

        $this->twinfield(ReplayClient::fixture('declarations-count'))->declarations()->vatReturnXml(1);
    }

    public function testSetsTheStatus(): void
    {
        $http = ReplayClient::fixture('declarations-set-to-sent');

        $this->twinfield($http)->declarations()->setToSent(2909);

        self::assertSame('http://www.twinfield.com/SetToSent', $http->lastRequest()->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('<SetToSent><documentId>2909</documentId></SetToSent>', $this->body($http));
    }

    public function testThrowsWhenTheStatusCannotChange(): void
    {
        $http = ReplayClient::fixture('declarations-set-to-rejected-error');

        try {
            $this->twinfield($http)->declarations()->setToRejected(2909, 'Wrong amount & period');
            self::fail('Expected a DeclarationException.');
        } catch (DeclarationException $exception) {
            self::assertSame(DeclarationStatusMessage::StartStatusInCorrectForTransition, $exception->messages[0]->code);
        }

        self::assertStringContainsString(
            '<SetToRejected><documentId>2909</documentId><reason>Wrong amount &amp; period</reason></SetToRejected>',
            $this->body($http),
        );
    }

    public function testRequiresAReasonToReject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->declarations()->setToRejected(2909, ' ');
    }

    public function testRejectsANegativeOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be 0 or more.');

        $this->twinfield(new ReplayClient())->declarations()->list(offset: -1);
    }

    public function testRejectsAnInvalidDocumentId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->declarations()->vatReturnXml(0);
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->declarations()->listForOrganisation();
    }

    /**
     * An empty list, as Twinfield gives it for an unknown company (`declarations-list-empty`).
     */
    private static function emptyList(string $operation): ResponseInterface
    {
        $xml = (string) ReplayClient::response('declarations-list-empty')->getBody();
        $psr17 = new Psr17Factory();

        return $psr17->createResponse()->withBody($psr17->createStream(str_replace('GetAllSummaries', $operation, $xml)));
    }

    private static function document(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));

        return $document;
    }

    private function body(ReplayClient $http): string
    {
        $body = (string) preg_replace('~>\s+<~', '><', (string) $http->lastRequest()->getBody());

        // Without the namespace prefixes, so the assertions read like the WSDL.
        return (string) preg_replace('~ xmlns(:\w+)?="[^"]*"~', '', $body);
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
