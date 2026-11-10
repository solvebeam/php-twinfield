<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BrowseData;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\BrowseQuery;
use SolveBeam\Twinfield\BrowseData\BrowseValueType;
use SolveBeam\Twinfield\BrowseData\SortOrder;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class BrowseQueryTest extends TestCase
{
    private const array COLUMNS = [
        'fin.trs.head.code',
        'fin.trs.head.number',
        'fin.trs.head.date',
        'fin.trs.head.yearperiod',
        'fin.trs.line.valuesigned',
        'fin.trs.head.modified',
        'fin.trs.head.status',
        'fin.trs.line.description',
        'fin.trs.line.quantity',
        'fin.trs.head.year',
    ];

    public function testBuildsTheRequest(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse(BrowseCode::GeneralLedgerDetailsV3)
            ->columns('fin.trs.head.code', 'fin.trs.head.status')
            ->between('fin.trs.head.yearperiod', Period::of(2026, 0), '2026/55')
            ->equal('fin.trs.head.status', 'normal')
            ->notBetween('fin.trs.line.dim1', 1000, 1999)
            ->like('fin.trs.line.dim2', '13*')
            ->sortBy('fin.trs.head.code')
            ->sortBy('fin.trs.head.number', SortOrder::Descending);

        self::assertSame('030_3', $query->code());
        self::assertSame(
            '<columns code="030_3" optimize="true">'
            . '<sort><field order="ascending">fin.trs.head.code</field>'
            . '<field order="descending">fin.trs.head.number</field></sort>'
            . '<column><field>fin.trs.head.code</field><visible>true</visible></column>'
            . '<column><field>fin.trs.head.status</field><visible>true</visible>'
            . '<operator>equal</operator><from>normal</from></column>'
            . '<column><field>fin.trs.head.yearperiod</field>'
            . '<operator>between</operator><from>2026/00</from><to>2026/55</to></column>'
            . '<column><field>fin.trs.line.dim1</field>'
            . '<operator>notbetween</operator><from>1000</from><to>1999</to></column>'
            . '<column><field>fin.trs.line.dim2</field><operator>like</operator><from>13*</from></column>'
            . '</columns>',
            $query->toXml(),
        );
    }

    public function testEscapesValues(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse('030_3')
            ->columns('fin.trs.head.code')
            ->equal('fin.trs.line.description', 'A&B <c>');

        self::assertStringContainsString('<from>A&amp;B &lt;c&gt;</from>', $query->toXml());
    }

    public function testFormatsDatesAndDateTimesInUtc(): void
    {
        $amsterdam = new DateTimeZone('Europe/Amsterdam');

        $query = $this->twinfield(new ReplayClient())->browse('030_3')
            ->columns('fin.trs.head.code')
            ->betweenDates(
                'fin.trs.head.date',
                new DateTimeImmutable('2026-01-01'),
                new DateTimeImmutable('2026-01-31'),
            )
            ->betweenDateTimes(
                'fin.trs.head.modified',
                new DateTimeImmutable('2026-04-28 00:30:00', $amsterdam),
                new DateTimeImmutable('2026-04-29 00:30:00', $amsterdam),
            )
            ->equal('fin.trs.head.regime', Regime::Fiscal);

        $xml = $query->toXml();

        self::assertStringContainsString('<from>20260101</from><to>20260131</to>', $xml);
        self::assertStringContainsString('<from>20260427223000</from><to>20260428223000</to>', $xml);
        self::assertStringContainsString('<from>Fiscal</from>', $xml);
    }

    public function testIsImmutableAndReplacesCriteria(): void
    {
        $base = $this->twinfield(new ReplayClient())->browse('030_3')->columns('fin.trs.head.code');

        $first = $base->equal('fin.trs.head.status', 'normal');
        $second = $first->equal('fin.trs.head.status', 'final');

        self::assertStringNotContainsString('fin.trs.head.status', $base->toXml());
        self::assertStringContainsString('<from>normal</from>', $first->toXml());
        self::assertStringContainsString('<from>final</from>', $second->toXml());
        self::assertStringNotContainsString('normal', $second->toXml());
    }

    public function testSortingAFieldAgainMovesItToTheEnd(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse('000')
            ->columns('fin.trs.head.code')
            ->sortBy('fin.trs.head.code')
            ->sortBy('fin.trs.head.number')
            ->sortBy('fin.trs.head.code', SortOrder::Descending);

        self::assertStringContainsString(
            '<sort><field order="ascending">fin.trs.head.number</field>'
            . '<field order="descending">fin.trs.head.code</field></sort>',
            $query->toXml(),
        );
    }

    public function testRefusesAFourthSortField(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse('000')
            ->sortBy('fin.trs.head.code')
            ->sortBy('fin.trs.head.number')
            ->sortBy('fin.trs.line.dim1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 3 fields');

        $query->sortBy('fin.trs.line.dim2');
    }

    public function testRefusesInvalidInput(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse('030_3');

        $refused = [
            'empty field' => static fn () => $query->columns(''),
            'field without dot' => static fn () => $query->columns('code'),
            'duplicate column' => static fn () => $query->columns('fin.trs.head.code', 'fin.trs.head.code'),
            'empty value' => static fn () => $query->equal('fin.trs.head.code', ''),
            'empty to' => static fn () => $query->between('fin.trs.head.number', '1', ''),
            'value over 40 characters' => static fn () => $query->equal(
                'fin.trs.line.description',
                str_repeat('x', 41),
            ),
            'other company' => static fn () => $query->equal('fin.trs.head.office', '1001'),
            'office between' => static fn () => $query->between('fin.trs.head.office', '1000', '1000'),
            'empty code' => fn () => new BrowseQuery($this->twinfield(new ReplayClient()), ''),
        ];

        foreach ($refused as $case => $call) {
            try {
                $call();
                self::fail(sprintf('Expected an InvalidArgumentException for %s.', $case));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAllowsTheCompanyOfTheClientAsOfficeCriterion(): void
    {
        $query = $this->twinfield(new ReplayClient())->browse('030_3')->equal('fin.trs.head.office', '1000');

        self::assertStringContainsString('<from>1000</from>', $query->toXml());
    }

    public function testNeedsColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one column');

        $this->twinfield(new ReplayClient())->browse('030_3')->get();
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $provider = TestTokens::provider('https://example.test');
        $twinfield = Twinfield::create($provider, new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);

        $twinfield->browse('030_3')->columns('fin.trs.head.code')->get();
    }

    public function testReadsTransactionLines(): void
    {
        $http = ReplayClient::fixture('browse-transaction-lines');

        $query = $this->twinfield($http)->browse('030_3')
            ->columns(...self::COLUMNS)
            ->between('fin.trs.head.yearperiod', '2026/00', '2026/55');

        $result = $query->get();

        self::assertSame($query->toXml(), $this->xmlRequest($http->lastRequest()));
        self::assertSame('https://example.test/webservices/processxml.asmx', (string) $http->lastRequest()->getUri());
        self::assertCount(3, $result);
        self::assertSame(self::COLUMNS, array_map(static fn ($column) => $column->field, $result->columns));
        self::assertSame(BrowseValueType::Value, $result->columns[4]->type);
        self::assertSame(BrowseValueType::DateTime, $result->columns[5]->type);
        self::assertSame(BrowseValueType::Number, $result->columns[9]->type);

        [$first, $second, $draft] = iterator_to_array($result);

        self::assertSame('VRK', $first->string('fin.trs.head.code'));
        self::assertSame(202600003, $first->int('fin.trs.head.number'));
        self::assertSame('2026-02-25', $first->date('fin.trs.head.date')->format('Y-m-d'));
        self::assertSame('2026/02', (string) $first->period('fin.trs.head.yearperiod'));
        self::assertSame('121.00', (string) $first->amount('fin.trs.line.valuesigned'));
        self::assertSame('-100.00', (string) $second->amount('fin.trs.line.valuesigned'));
        self::assertSame('2026-02-25T12:33:50+00:00', $first->dateTime('fin.trs.head.modified')->format(DATE_ATOM));
        self::assertSame('final', $first->string('fin.trs.head.status'));
        self::assertSame('Definitief', $first->displayName('fin.trs.head.status'));
        self::assertNull($first->displayName('fin.trs.head.code'));
        self::assertSame('Invoice 2026-003', $first->nullableString('fin.trs.line.description'));
        self::assertNull($second->nullableString('fin.trs.line.description'));
        self::assertNull($first->nullableDecimal('fin.trs.line.quantity'));
        self::assertSame('2.5', $second->nullableDecimal('fin.trs.line.quantity'));
        self::assertSame(2026, $first->int('fin.trs.head.year'));
        self::assertTrue($first->has('fin.trs.head.code'));
        self::assertFalse($first->has('fin.trs.line.dim1'));
        self::assertSame('VRK', $first->values()['fin.trs.head.code']);

        self::assertTrue($first->company->equals(TestTokens::company('1000')));
        self::assertNotNull($first->transactionLine);
        self::assertSame('VRK', $first->transactionLine->dayBook);
        self::assertSame(202600003, $first->transactionLine->number);
        self::assertSame(1, $first->transactionLine->line);
        self::assertSame(2, $second->transactionLine?->line);
        self::assertSame(202600003, $first->transactionLine->transaction()->number);

        self::assertSame(-1, $draft->int('fin.trs.head.number'));
        self::assertTrue($draft->transactionLine?->isDraft());
    }

    public function testReadersCheckFieldsAndTypes(): void
    {
        $row = $this->twinfield(ReplayClient::fixture('browse-transaction-lines'))->browse('030_3')
            ->columns(...self::COLUMNS)
            ->get()
            ->rows[1];

        $wrong = [
            'unknown field' => static fn () => $row->string('fin.trs.line.dim1'),
            'amount of a string' => static fn () => $row->amount('fin.trs.head.code'),
            'date of a datetime' => static fn () => $row->date('fin.trs.head.modified'),
            'int of a value' => static fn () => $row->int('fin.trs.line.valuesigned'),
        ];

        foreach ($wrong as $case => $call) {
            try {
                $call();
                self::fail(sprintf('Expected an InvalidArgumentException for %s.', $case));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('fin.trs.line.description is empty.');

        $row->string('fin.trs.line.description');
    }

    public function testReadsTotalsWithoutTransactionLine(): void
    {
        $result = $this->twinfield(ReplayClient::fixture('browse-totals'))->browse(BrowseCode::AnnualReportTotals)
            ->columns('fin.trs.line.dim1', 'fin.trs.line.basevaluesigned')
            ->get();

        self::assertCount(2, $result);
        self::assertNull($result->rows[0]->transactionLine);
        self::assertSame('1300', $result->rows[0]->string('fin.trs.line.dim1'));
        self::assertSame('-726.47', (string) $result->rows[1]->amount('fin.trs.line.basevaluesigned'));
    }

    public function testReadsAnEmptyResult(): void
    {
        $result = $this->twinfield(ReplayClient::fixture('browse-empty'))->browse('030_3')
            ->columns('fin.trs.head.code')
            ->get();

        self::assertCount(0, $result);
        self::assertSame([], $result->rows);
    }

    public function testThrowsTheErrorOfTwinfield(): void
    {
        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Veld fin.trs.nope bestaat niet.');

        $this->twinfield(ReplayClient::fixture('browse-unknown-field'))
            ->browse('030_3')
            ->columns('fin.trs.nope')
            ->get();
    }

    public function testThrowsForRowsOfAnotherCompany(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('tr[1]/key/office is 1001, expected the company of the client, 1000.');

        $this->twinfield(ReplayClient::fixture('browse-other-company'))->browse('030_3')
            ->columns('fin.trs.head.code')
            ->get();
    }

    public function testThrowsWhenRowsAreMissing(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Twinfield returned rows 1 to 1 of 2 (1 rows), expected all rows.');

        $this->twinfield(ReplayClient::fixture('browse-incomplete'))->browse('030_3')
            ->columns('fin.trs.head.code')
            ->get();
    }

    public function testThrowsForOtherColumnsThanAskedFor(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Asked for the columns fin.trs.head.number, got fin.trs.head.code.');

        $this->twinfield(ReplayClient::fixture('browse-empty'))->browse('030_3')
            ->columns('fin.trs.head.number')
            ->get();
    }

    public function testThrowsForAnUnknownType(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('@type is "Money"');

        $this->twinfield(ReplayClient::fixture('browse-unknown-type'))->browse('030_3')
            ->columns('fin.trs.line.valuesigned')
            ->get();
    }

    public function testReadsAResponseOverTenMegabytes(): void
    {
        // libxml drops text over 10 MB without LIBXML_PARSEHUGE: a large result must be complete.
        $rows = str_repeat('<tr><td>' . str_repeat('x', 1000) . '</td><key><office>1000</office></key></tr>', 12_000);
        $inner = '<browse result="1" first="1" last="12000" total="12000"><th>'
            . '<td label="" hideforuser="false" type="String">fin.trs.line.description</td></th>' . $rows . '</browse>';
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<ProcessXmlStringResponse xmlns="http://www.twinfield.com/"><ProcessXmlStringResult>'
            . htmlspecialchars($inner, ENT_NOQUOTES | ENT_XML1)
            . '</ProcessXmlStringResult></ProcessXmlStringResponse></soap:Body></soap:Envelope>';

        $http = new ReplayClient(new Response(200, ['Content-Type' => 'text/xml; charset=utf-8'], $body));

        $result = $this->twinfield($http)->browse('030_3')->columns('fin.trs.line.description')->get();

        self::assertCount(12_000, $result);
        self::assertSame(str_repeat('x', 1000), $result->rows[11_999]->string('fin.trs.line.description'));
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        $xmlRequest = $xpath->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
