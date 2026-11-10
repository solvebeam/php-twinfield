<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Integration\Declaration;

use DOMDocument;
use SolveBeam\Twinfield\Declaration\DeclarationException;
use SolveBeam\Twinfield\Declaration\DeclarationLoadMessage;
use SolveBeam\Twinfield\Declaration\DeclarationResource;
use SolveBeam\Twinfield\Declaration\DeclarationSummary;
use SolveBeam\Twinfield\Declaration\DeclarationType;
use SolveBeam\Twinfield\Soap\ClassicServiceFaultException;
use SolveBeam\Twinfield\Tests\Support\IntegrationTestCase;

/**
 * Live declarations queries (read-only). The returns are looked up in the whole organisation
 * (`*`) of last year, since the test company may have none; a test is skipped when the
 * organisation has no return of the kind it needs. The status commands are in the write tests.
 */
final class DeclarationResourceTest extends IntegrationTestCase
{
    public function testListsReturnsOfTheOrganisationById(): void
    {
        $year = (int) date('Y') - 1;
        $returns = $this->declarations()->listForOrganisation(limit: 3, year: $year);

        self::assertNotEmpty($returns, 'The organisation has returns of last year.');
        self::assertLessThanOrEqual(3, count($returns));

        $ids = array_map(static fn (DeclarationSummary $return): int => $return->id, $returns);
        $sorted = $ids;
        sort($sorted);

        self::assertSame($sorted, $ids, 'Twinfield gives the returns by ID.');

        foreach ($returns as $return) {
            self::assertSame($year, $return->year);
            self::assertTrue($return->company->organisation->equals($this->twinfield->organisation()));
        }
    }

    public function testOffsetIsZeroBased(): void
    {
        $year = (int) date('Y') - 1;
        $first = $this->declarations()->listForOrganisation(offset: 0, limit: 2, year: $year);
        $second = $this->declarations()->listForOrganisation(offset: 1, limit: 1, year: $year);

        self::assertCount(2, $first);
        self::assertEquals([$first[1]], $second);
    }

    public function testCountsTheReturnsOfACompany(): void
    {
        $return = $this->find(DeclarationType::VatTurnover);
        $declarations = $this->twinfield->company($return->company)->declarations();
        $all = $declarations->all();

        self::assertSame(count($all), $declarations->count());
        self::assertContainsEquals($return, $all);
    }

    public function testDownloadsAVatReturn(): void
    {
        $return = $this->find(DeclarationType::VatTurnover);

        self::assertRoot(
            'http://www.xbrl.org/2003/instance',
            'xbrl',
            $this->declarations()->vatReturnXbrl($return->id),
        );
        self::assertRoot('', 'AangifteOmzetbelasting', $this->declarations()->vatReturnXml($return->id));
        self::assertMatchesRegularExpression(
            '~^\d+\+$~',
            (string) $this->declarations()->vatReturnPaymentReference($return->id),
        );
    }

    public function testDownloadsAnIctReturn(): void
    {
        $return = $this->find(DeclarationType::Ict);

        self::assertRoot(
            'http://www.xbrl.org/2003/instance',
            'xbrl',
            $this->declarations()->ictReturnXbrl($return->id),
        );
        self::assertRoot(
            'http://xml.belastingdienst.nl/schemas/ICP/2010/01',
            'OpgaafICP',
            $this->declarations()->ictReturnXml($return->id),
        );
    }

    public function testDownloadsATaxGroupReturn(): void
    {
        $return = $this->find(DeclarationType::TaxGroup);

        self::assertRoot(
            'http://www.xbrl.org/2003/instance',
            'xbrl',
            $this->declarations()->taxGroupVatReturnXbrl($return->id),
        );
        self::assertRoot('', 'AangifteOmzetbelasting', $this->declarations()->taxGroupVatReturnXml($return->id));
        self::assertMatchesRegularExpression(
            '~^\d+\+$~',
            (string) $this->declarations()->taxGroupVatReturnPaymentReference($return->id),
        );
    }

    public function testYearlySupplementationHasNoPaymentReference(): void
    {
        $return = $this->find(DeclarationType::YearlySupplementation);

        self::assertNull($this->declarations()->vatReturnPaymentReference($return->id));
    }

    public function testIctReturnHasNoPaymentReference(): void
    {
        $return = $this->find(DeclarationType::Ict);

        try {
            $this->declarations()->vatReturnPaymentReference($return->id);
            self::fail('Expected a DeclarationException.');
        } catch (DeclarationException $exception) {
            self::assertSame(DeclarationLoadMessage::InvalidDocumentType, $exception->messages[0]->code);
        }
    }

    public function testUnknownDocumentIsASoapFault(): void
    {
        $this->expectException(ClassicServiceFaultException::class);

        $this->declarations()->vatReturnXbrl(999999999);
    }

    private function declarations(): DeclarationResource
    {
        if ($this->twinfield->companyCode() === null) {
            self::markTestSkipped('Declarations need a company: set TWINFIELD_COMPANY.');
        }

        return $this->twinfield->declarations();
    }

    /**
     * The last return of this kind in the organisation, of last year.
     */
    private function find(DeclarationType $type): DeclarationSummary
    {
        $returns = array_filter(
            $this->declarations()->listForOrganisation(limit: 10000, year: (int) date('Y') - 1),
            static fn (DeclarationSummary $return): bool => $return->type === $type,
        );

        if ($returns === []) {
            self::markTestSkipped(sprintf('The organisation has no %s return of last year.', $type->value));
        }

        return end($returns);
    }

    private static function assertRoot(string $namespace, string $name, string $xml): void
    {
        $document = new DOMDocument();

        self::assertTrue($document->loadXML($xml), 'A standalone XML document.');
        self::assertNotNull($document->documentElement);
        self::assertSame($name, $document->documentElement->localName);
        self::assertSame($namespace, (string) $document->documentElement->namespaceURI);
    }
}
