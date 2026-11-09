<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\ElectronicBankStatement;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\ElectronicBankStatement\CreatedStatement;
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementInput;
use SolveBeam\Twinfield\ElectronicBankStatement\FailedStatement;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\Http\WriteGuardClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class ElectronicBankStatementResourceTest extends TestCase
{
    public function testCreatesAStatement(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create');

        $statement = $this->twinfield($http)->electronicBankStatements()->create(self::input());

        self::assertSame(
            '<statements importduplicate="0"><statement target="electronicstatements">'
            . '<iban>NL91ABNA0417164300</iban><date>20261109</date><currency>EUR</currency>'
            . '<statementnumber>2</statementnumber><office>1000</office>'
            . '<startvalue>7.68</startvalue><closevalue>151.18</closevalue>'
            . '<transactions>'
            . '<transaction><contraaccount name="Example customer">1234567890</contraaccount><type>N100</type>'
            . '<debitcredit>credit</debitcredit><value>151.00</value><description>Invoice 2026-0042</description></transaction>'
            . '<transaction><contraiban>NL20INGB0001234567</contraiban><type>N999</type>'
            . '<debitcredit>debit</debitcredit><value>7.50</value><description>Bank costs</description></transaction>'
            . '</transactions></statement></statements>',
            $this->xmlRequest($http->lastRequest()),
        );

        self::assertTrue($statement->company->equals(TestTokens::company('1000')));
        self::assertSame('BNK', $statement->bankBook);
        self::assertSame('Bank', $statement->bankBookName);
        self::assertSame('NL91ABNA0417164300', $statement->iban);
        self::assertNull($statement->account);
        self::assertSame('2026-11-09', $statement->date->format('Y-m-d'));
        self::assertSame('EUR', $statement->currency);
        self::assertSame(2, $statement->number);
        self::assertSame('7.68', $statement->startValue->value);
        self::assertSame('151.18', $statement->closeValue->value);
        self::assertSame('143.50', $statement->total()->value);
        self::assertCount(2, $statement->lines);

        [$received, $paid] = $statement->lines;

        self::assertSame(DebitCredit::Credit, $received->debitCredit);
        self::assertSame('151.00', $received->value->value);
        self::assertSame('Invoice 2026-0042', $received->description);
        self::assertSame('N100', $received->type);
        self::assertNull($received->reference);
        self::assertNull($received->contraAccount, 'The response has no contra account, as in the documentation.');
        self::assertSame('1300', $received->dimension(1)?->code);
        self::assertSame('BAS', $received->dimension(1)->type);
        self::assertSame('1000', $received->dimension(2)?->code);
        self::assertTrue($received->dimension(2)->isCustomer());
        self::assertSame('Example customer', $received->dimension(2)->name);
        self::assertNull($received->dimension(3));

        self::assertSame(DebitCredit::Debit, $paid->debitCredit);
        self::assertSame('-7.50', $paid->signedValue()->value);
        self::assertSame('2300', $paid->dimension(1)?->code);
    }

    public function testCreatesAMinimalStatementForABankBook(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create');

        $this->twinfield($http)->electronicBankStatements()->create(
            ElectronicBankStatementInput::forBankBook('BNK')->receive('10.00'),
            importDuplicate: true,
        );

        self::assertSame(
            '<statements importduplicate="1"><statement target="electronicstatements">'
            . '<code>BNK</code><office>1000</office>'
            . '<transactions><transaction><debitcredit>credit</debitcredit><value>10.00</value></transaction></transactions>'
            . '</statement></statements>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testSendsTheAccountNumberAndSortCode(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create');

        $this->twinfield($http)->electronicBankStatements()->create(
            ElectronicBankStatementInput::forAccount('417164300')
                ->sortCode('123456')
                ->pay('1.00', reference: 'REF-1', endToEndId: 'E2E-1', returnReason: 'AC04'),
        );

        self::assertSame(
            '<statements importduplicate="0"><statement target="electronicstatements">'
            . '<account>417164300</account><office>1000</office><sortcode>123456</sortcode>'
            . '<transactions><transaction><reference>REF-1</reference><debitcredit>debit</debitcredit>'
            . '<value>1.00</value><endtoendid>E2E-1</endtoendid><returnreason>AC04</returnreason></transaction></transactions>'
            . '</statement></statements>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testThrowsWhenTwinfieldRefusesTheStatement(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create-failed');

        $this->expectException(ProcessXmlException::class);
        $this->expectExceptionMessage('Er is geen bankboek met dit rekeningnummer.');

        $this->twinfield($http)->electronicBankStatements()->create(
            ElectronicBankStatementInput::forIban('NL02ABNA0123456789')->receive('10.00', 'Payment'),
        );
    }

    public function testCreatesManyAndReportsFailuresPerStatement(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create-many');
        $failing = ElectronicBankStatementInput::forIban('NL02ABNA0123456789')->receive('10.00', 'Payment');

        $result = $this->twinfield($http)->electronicBankStatements()->createMany([self::input(), $failing]);

        self::assertSame(2, substr_count($this->xmlRequest($http->lastRequest()), '<statement target="electronicstatements">'));
        self::assertCount(2, $result);
        self::assertFalse($result->allCreated());
        self::assertInstanceOf(CreatedStatement::class, $result->results[0]);
        self::assertSame('BNK', $result->results[0]->statement->bankBook);
        self::assertInstanceOf(FailedStatement::class, $result->results[1]);
        self::assertSame($failing, $result->results[1]->input);
        self::assertSame(
            'statements/statement[2]/iban: Er is geen bankboek met dit rekeningnummer.',
            $result->results[1]->message(),
        );
    }

    public function testCreateManyWithoutInputsSendsNothing(): void
    {
        $http = new ReplayClient();

        self::assertCount(0, $this->twinfield($http)->electronicBankStatements()->createMany([]));
        self::assertSame([], $http->requests);
    }

    public function testThrowsWhenTheResponseHasAnotherNumberOfStatements(): void
    {
        $http = ReplayClient::fixture('electronic-bank-statement-create');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Sent 2 bank statements, got 1 back.');

        $this->twinfield($http)->electronicBankStatements()->createMany([self::input(), self::input()]);
    }

    public function testThrowsOnAnotherRootElement(): void
    {
        $http = ReplayClient::fixture('reversal-transaction-get');

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected a <statements> response, got <reverse>.');

        $this->twinfield($http)->electronicBankStatements()->create(self::input());
    }

    public function testRefusesAStatementWithoutLinesBeforeSending(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->electronicBankStatements()->create(ElectronicBankStatementInput::forBankBook('BNK'));
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('at least one line', $exception->getMessage());
            self::assertSame([], $http->requests);
        }
    }

    public function testTheWriteGuardTreatsStatementsAsAWrite(): void
    {
        $body = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<ProcessXmlString xmlns="http://www.twinfield.com/"><xmlRequest>'
            . htmlspecialchars('<statements importduplicate="0"><statement target="electronicstatements"><code>BNK</code><office>1000</office></statement></statements>')
            . '</xmlRequest></ProcessXmlString></soap:Body></soap:Envelope>';

        self::assertFalse(WriteGuardClient::isReadOnly('ProcessXmlString', $body));
    }

    public function testNeedsACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(TestTokens::provider('https://example.test'), new ReplayClient(), $psr17, $psr17);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The client has no company');

        $twinfield->electronicBankStatements()->create(self::input());
    }

    private static function input(): ElectronicBankStatementInput
    {
        return ElectronicBankStatementInput::forIban('NL91ABNA0417164300')
            ->date(new DateTimeImmutable('2026-11-09'))
            ->currency('EUR')
            ->number(2, startValue: '7.68', closeValue: '151.18')
            ->receive('151.00', 'Invoice 2026-0042', type: 'N100', contraAccount: '1234567890', contraName: 'Example customer')
            ->pay('7.50', 'Bank costs', type: 'N999', contraIban: 'NL20INGB0001234567');
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
