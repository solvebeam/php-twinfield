<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\VatCode;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Authentication\StaticAccessTokenProvider;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Office\NamedReference;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;
use SolveBeam\Twinfield\VatCode\VatAccountInput;
use SolveBeam\Twinfield\VatCode\VatAccountLineType;
use SolveBeam\Twinfield\VatCode\VatCodeAlreadyExistsException;
use SolveBeam\Twinfield\VatCode\VatCodeInput;
use SolveBeam\Twinfield\VatCode\VatCodeNotFoundException;
use SolveBeam\Twinfield\VatCode\VatCodeStatus;
use SolveBeam\Twinfield\VatCode\VatCodeSummary;
use SolveBeam\Twinfield\VatCode\VatCodeType;
use SolveBeam\Twinfield\VatCode\VatPercentageInput;

final class VatCodeResourceTest extends TestCase
{
    public function testSearchesVatCodes(): void
    {
        $http = ReplayClient::fixture('finder-vat-codes');

        $page = $this->twinfield($http)->vatCodes()
            ->search('V*')
            ->inCode()
            ->vatType(VatCodeType::Sales)
            ->limit(10)
            ->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new VatCodeSummary('VH', 'BTW Hoog'),
            new VatCodeSummary('VL', 'BTW 9%'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertSame('VAT', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('V*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame('10', $xpath->evaluate('string(//tw:Search/tw:maxRows)'));
        self::assertSame('vattype', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[1])'));
        self::assertSame('sales', $xpath->evaluate('string(//tw:Search/tw:options/tw:ArrayOfString/tw:string[2])'));
    }

    public function testSearchSendsNoOptionsByDefault(): void
    {
        $http = ReplayClient::fixture('finder-vat-codes');

        $this->twinfield($http)->vatCodes()->search()->get();

        self::assertSame(
            0.0,
            $this->soapXpath($http->lastRequest())->evaluate('count(//tw:Search/tw:options/tw:ArrayOfString)'),
            'The company comes from the header.',
        );
    }

    public function testReadsAVatCode(): void
    {
        $http = ReplayClient::fixture('vat-read');

        $vatCode = $this->twinfield($http)->vatCodes()->get('VH');

        self::assertSame(
            '<read><office>1000</office><type>vat</type><code>VH</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertSame('1000', $vatCode->company, 'The response has no office: it is the company of the request.');
        self::assertSame('VH', $vatCode->code);
        self::assertSame('BTW Hoog', $vatCode->name);
        self::assertSame('V Hoog', $vatCode->shortName);
        self::assertSame(VatCodeType::Sales, $vatCode->type);
        self::assertSame(VatCodeStatus::Active, $vatCode->status);
        self::assertSame('1dc16239-0000-4a72-946f-b6a15c810c5c', $vatCode->uid);
        self::assertEquals(new DateTimeImmutable('2004-10-27 10:13:42'), $vatCode->created);
        self::assertEquals(new DateTimeImmutable('2004-10-27 10:13:42'), $vatCode->modified);
        self::assertSame(0, $vatCode->touched);
        self::assertSame('SUPER', $vatCode->modifiedBy);

        self::assertCount(2, $vatCode->percentages);
        [$old, $current] = $vatCode->percentages;

        self::assertEquals(new DateTimeImmutable('2000-01-01'), $old->from);
        self::assertSame('19', $old->percentage->value, 'Twinfield sends 19.000000000.');
        self::assertSame('BTW 19%', $old->name);
        self::assertSame('V 19%', $old->shortName);
        self::assertSame(VatCodeStatus::Active, $old->status);
        self::assertTrue($old->inUse);
        self::assertEquals(new DateTimeImmutable('2010-12-14 18:28:56'), $old->created);
        self::assertSame('TWINFIELD', $old->createdBy);
        self::assertNull($old->discountAccount);
        self::assertNull($old->writeOffAccount);

        self::assertSame('21', $current->percentage->value);
        self::assertCount(1, $current->accounts);
        $account = $current->accounts[0];
        self::assertSame(1, $account->id);
        self::assertSame(VatAccountLineType::Vat, $account->lineType);
        self::assertEquals(
            new NamedReference('16615', 'BTW over leveringen / diensten belast met BTW hoog'),
            $account->generalLedgerAccount,
        );
        self::assertEquals(new NamedReference('NL', 'Nederland'), $account->groupCountry);
        self::assertEquals(
            new NamedReference('NL1A', 'Leveringen/Diensten belast met hoog %', 'L/D Hoog'),
            $account->group,
        );
        self::assertSame('100', $account->percentage->value, 'Twinfield sends 100.0.');
    }

    public function testFindsThePercentageOfADate(): void
    {
        $vatCode = $this->twinfield(ReplayClient::fixture('vat-read'))->vatCodes()->get('VH');

        self::assertNull($vatCode->percentageOn(new DateTimeImmutable('1999-12-31')));
        self::assertSame('19', $vatCode->percentageOn(new DateTimeImmutable('2000-01-01'))?->percentage->value);
        self::assertSame('19', $vatCode->percentageOn(new DateTimeImmutable('2012-09-30 23:59'))?->percentage->value);
        self::assertSame('21', $vatCode->percentageOn(new DateTimeImmutable('2012-10-01'))?->percentage->value);
        self::assertSame('21', $vatCode->currentPercentage()?->percentage->value);
        self::assertSame('21', $vatCode->latestPercentage()->percentage->value);

        self::assertSame('21.00', $vatCode->vatOn('100.00', new DateTimeImmutable('2026-03-15'))->value);
        self::assertSame('19.00', $vatCode->vatOn('100', new DateTimeImmutable('2011-01-01'))->value);
    }

    public function testVatOnThrowsBeforeTheFirstPercentage(): void
    {
        $vatCode = $this->twinfield(ReplayClient::fixture('vat-read'))->vatCodes()->get('VH');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VAT code VH has no percentage on 1999-12-31: the first one starts on 2000-01-01.');

        $vatCode->vatOn('100.00', new DateTimeImmutable('1999-12-31'));
    }

    public function testReadsAReverseChargeVatCode(): void
    {
        $vatCode = $this->twinfield(ReplayClient::fixture('vat-read-reverse-charge'))->vatCodes()->get('ICV-H');

        $accounts = $vatCode->latestPercentage()->accounts;

        self::assertCount(2, $accounts);
        self::assertSame([1, 2], [$accounts[0]->id, $accounts[1]->id]);
        self::assertSame('-100', $accounts[0]->percentage->value);
        self::assertSame('NL4B', $accounts[0]->group?->code);
        self::assertSame('100', $accounts[1]->percentage->value);
        self::assertSame('NL5B', $accounts[1]->group?->code);
    }

    public function testReadsAVatCodeWithoutShortNameAndVatGroup(): void
    {
        $vatCode = $this->twinfield(ReplayClient::fixture('vat-read-without-group'))->vatCodes()->get('VZ');

        self::assertSame(VatCodeType::Purchase, $vatCode->type);
        self::assertNull($vatCode->shortName);

        $account = $vatCode->percentages[0]->accounts[0];
        self::assertNull($account->groupCountry);
        self::assertNull($account->group);
        self::assertSame('0', $vatCode->latestPercentage()->percentage->value);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('vat-read-not-found'))->vatCodes()->get('VX');
            self::fail('Expected a VatCodeNotFoundException.');
        } catch (VatCodeNotFoundException $exception) {
            self::assertSame('VX', $exception->vatCode);
            self::assertSame('Btw-code VX bestaat niet.', $exception->reason);
            self::assertSame('VAT code VX not found: Btw-code VX bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(ProcessXmlException::class, $exception->getPrevious());
        }
    }

    public function testFind(): void
    {
        self::assertNull($this->twinfield(ReplayClient::fixture('vat-read-not-found'))->vatCodes()->find('VX'));
        self::assertSame('VH', $this->twinfield(ReplayClient::fixture('vat-read'))->vatCodes()->find('VH')?->code);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unexpectedResponses(): iterable
    {
        yield 'unknown type' => ['<type>sales</type>', '<type>both</type>', 'vat/type is "both"'];
        yield 'unknown status' => ['<vat status="active"', '<vat status="archived"', 'vat/@status is "archived"'];
        yield 'empty name' => ['<name>BTW Hoog</name>', '<name></name>', 'vat/name is empty'];
        yield 'missing uid' => ['<uid>1dc16239-0000-4a72-946f-b6a15c810c5c</uid>', '', 'vat/uid is missing'];
        yield 'percentage with comma' => ['<percentage>21.0000', '<percentage>21,0000', 'percentage is "21,0000'];
        yield 'percentage above 100' => ['<percentage>21.0000', '<percentage>121.0000', 'must be 0 to 100, 121 given'];
        yield 'invalid date' => ['<date>20121001', '<date>2012-10-01', 'percentage[2]/date is "2012-10-01"'];
        yield 'same date twice' => ['<date>20121001', '<date>20000101', 'VAT code VH has two percentages from 2000-01-01.'];
        yield 'missing inuse' => [' inuse="true"><date>20121001', '><date>20121001', '@inuse is missing'];
        yield 'unknown line type' => ['<linetype>vat', '<linetype>total', 'account/linetype is "total"'];
        yield 'share above 100' => ['<percentage>100.0', '<percentage>200.0', 'must be -100 to 100 percent, 200 given'];
        yield 'account without id' => ['<account id="1">', '<account>', 'account/@id is missing'];
        yield 'group without country' => [
            '<groupcountry name="Nederland" shortname="">NL</groupcountry>',
            '<groupcountry />',
            'A VAT group needs both a country and a group code.',
        ];
        yield 'no accounts' => ['~<accounts>.*?</accounts>~', '<accounts></accounts>', 'must have at least one account'];
        yield 'no percentages' => ['~<percentages>.*</percentages>~', '<percentages></percentages>', 'at least one percentage'];
    }

    #[DataProvider('unexpectedResponses')]
    public function testThrowsOnUnexpectedResponses(string $search, string $replace, string $message): void
    {
        $fixture = (string) ReplayClient::response('vat-read')->getBody();
        $search = htmlspecialchars($search, ENT_NOQUOTES);
        $replace = htmlspecialchars($replace, ENT_NOQUOTES);

        // A search starting with ~ is a regular expression, e.g. to empty a list.
        $xml = str_starts_with($search, '~')
            ? (string) preg_replace($search, $replace, $fixture)
            : str_replace($search, $replace, $fixture);

        self::assertNotSame($fixture, $xml, 'The fixture must contain the text to replace.');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);

        $this->twinfield(new ReplayClient(new Response(200, [], $xml)))->vatCodes()->get('VH');
    }

    public function testSavesAndReadsBack(): void
    {
        $http = new ReplayClient(ReplayClient::response('vat-save'), ReplayClient::response('vat-read'));

        $vatCode = $this->twinfield($http)->vatCodes()->save(
            VatCodeInput::sales('VH', 'BTW 21%')
                ->shortName('VH 21%')
                ->percentage(
                    VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
                        ->shortName('VH 21%')
                        ->account(VatAccountInput::vat('1530')->group('NL', 'NL1A')),
                ),
        );

        self::assertSame('BTW Hoog', $vatCode->name, 'The VAT code is read back after saving.');
        self::assertCount(2, $http->requests);
        self::assertSame(
            '<vat><office>1000</office><code>VH</code><name>BTW 21%</name><shortname>VH 21%</shortname>'
            . '<type>sales</type><percentages><percentage><date>20260101</date><percentage>21</percentage>'
            . '<name>BTW 21%</name><shortname>VH 21%</shortname><accounts><account><dim1>1530</dim1>'
            . '<groupcountry>NL</groupcountry><group>NL1A</group><percentage>100</percentage>'
            . '<linetype>vat</linetype></account></accounts></percentage></percentages></vat>',
            $this->xmlRequest($http->requests[0]),
        );
        self::assertSame(
            '<read><office>1000</office><type>vat</type><code>VH</code></read>',
            $this->xmlRequest($http->requests[1]),
        );
    }

    public function testSaveThrowsTheRefusalWithTheMessagesPerElement(): void
    {
        $input = VatCodeInput::sales('VH', 'BTW 21%')->percentage(
            VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
                ->account(VatAccountInput::vat('9999')),
        );

        try {
            $this->twinfield(ReplayClient::fixture('vat-save-error'))->vatCodes()->save($input);
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame('Grootboekrekening 9999 bestaat niet.', $exception->result->errors()[0]->text);
        }
    }

    public function testCreateReadsFirstAndRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('vat-read');

        try {
            $this->twinfield($http)->vatCodes()->create(VatCodeInput::sales('VH', 'BTW 21%'));
            self::fail('Expected a VatCodeAlreadyExistsException.');
        } catch (VatCodeAlreadyExistsException $exception) {
            self::assertSame('VH', $exception->vatCode);
            self::assertSame('VAT code VH already exists.', $exception->getMessage());
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testCreatesANewCode(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('vat-read-not-found'),
            ReplayClient::response('vat-save'),
            ReplayClient::response('vat-read'),
        );

        $this->twinfield($http)->vatCodes()->create(
            VatCodeInput::sales('VH', 'BTW 21%')->percentage(
                VatPercentageInput::from(new DateTimeImmutable('2026-01-01'), '21')
                    ->account(VatAccountInput::vat('1530')),
            ),
        );

        self::assertCount(3, $http->requests);
        self::assertStringStartsWith('<vat><office>1000</office><code>VH</code>', $this->xmlRequest($http->requests[1]));
    }

    public function testUpdateThrowsNotFoundWithoutSaving(): void
    {
        $http = ReplayClient::fixture('vat-read-not-found');

        try {
            $this->twinfield($http)->vatCodes()->update(VatCodeInput::sales('VX', 'Missing'));
            self::fail('Expected a VatCodeNotFoundException.');
        } catch (VatCodeNotFoundException) {
            self::assertCount(1, $http->requests);
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('vat-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->vatCodes()->delete('VH'));
        self::assertSame(
            '<vat status="deleted"><office>1000</office><code>VH</code></vat>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAVatCodeInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('vat-delete-hidden'))->vatCodes()->delete('VH'),
        );
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->expectException(VatCodeNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('vat-read-not-found'))->vatCodes()->delete('VX');
    }

    public function testDeleteThrowsWhenTheVatCodeStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('VAT code VH is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('vat-activate'))->vatCodes()->delete('VH');
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('vat-activate');

        $this->twinfield($http)->vatCodes()->activate('VH');

        self::assertSame(
            '<vat status="active"><office>1000</office><code>VH</code></vat>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            new StaticAccessTokenProvider('token-123', 'https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('VAT codes belong to a company: use company() first.');

        $twinfield->vatCodes()->get('VH');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(
            new StaticAccessTokenProvider('token-123', 'https://example.test'),
            $http,
            $psr17,
            $psr17,
        )->company('1000');
    }

    private function soapXpath(RequestInterface $request): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML((string) $request->getBody());

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('tw', 'http://www.twinfield.com/');

        return $xpath;
    }

    private function xmlRequest(RequestInterface $request): string
    {
        $xmlRequest = $this->soapXpath($request)->evaluate('string(//tw:ProcessXmlString/tw:xmlRequest)');

        self::assertIsString($xmlRequest);

        return $xmlRequest;
    }
}
