<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\BankBook;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\BankBook\AutomaticBankProcessing;
use SolveBeam\Twinfield\BankBook\AutomaticBankProcessingSignUpState;
use SolveBeam\Twinfield\BankBook\BankAccount;
use SolveBeam\Twinfield\BankBook\BankAccountInput;
use SolveBeam\Twinfield\BankBook\BankBook;
use SolveBeam\Twinfield\BankBook\BankBookAlreadyExistsException;
use SolveBeam\Twinfield\BankBook\BankBookInput;
use SolveBeam\Twinfield\BankBook\BankBookNotFoundException;
use SolveBeam\Twinfield\BankBook\BankBookServiceFaultCode;
use SolveBeam\Twinfield\BankBook\BankBookSummary;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class BankBookResourceTest extends TestCase
{
    private const string DATA_NS = 'http://schemas.datacontract.org/2004/07/Twinfield.WebServices.BankBookService';

    public function testSearchesBankBooksWithoutCashBooks(): void
    {
        $http = ReplayClient::fixture('finder-bank-books');

        $page = $this->twinfield($http)->bankBooks()->search('*')->inName()->limit(10)->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new BankBookSummary(TestTokens::company(), 'ING', 'ING 3255112'),
            new BankBookSummary(TestTokens::company(), 'RABO', 'Rabobank 123456789'),
        ], iterator_to_array($page));

        $body = $this->body($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString('<type>BNK</type>', $body);
        self::assertStringContainsString('<field>2</field>', $body);
        self::assertStringContainsString('<string>banktype</string><string>0</string>', $body);
    }

    public function testGetsABankBook(): void
    {
        $http = ReplayClient::fixture('bankbook-get');

        $bankBook = $this->twinfield($http)->bankBooks()->get('ING');

        self::assertEquals(new BankBook(
            company: TestTokens::company('1000'),
            code: 'ING',
            name: 'ING 3255112',
            shortName: 'ING',
            generalLedgerAccount: '1100',
            bankAccount: new BankAccount(
                name: 'Example B.V.',
                number: '3255112',
                iban: 'NL45INGB0003255112',
                bic: 'INGBNL2A',
                nbic: null,
                country: 'NL',
                referenceNumber: null,
            ),
            automaticBankProcessing: new AutomaticBankProcessing(
                signUpState: AutomaticBankProcessingSignUpState::Active,
                processStatements: true,
                processPayments: true,
                processDirectDebits: false,
            ),
        ), $bankBook);

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/BankBookService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/BankBookService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('i:type="a:GetBankBook"><a:Code>ING</a:Code></Query>', $this->body($request));
    }

    public function testReadsEmptyValuesAsNull(): void
    {
        $bankBook = $this->twinfield(ReplayClient::fixture('bankbook-get-default'))->bankBooks()->get('BNK');

        self::assertNull($bankBook->shortName);
        self::assertNull($bankBook->generalLedgerAccount);
        self::assertEquals(new BankAccount('Bank', '.', null, null, null, null, null), $bankBook->bankAccount);
    }

    public function testThrowsOnUnexpectedResponses(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('bankbook-get-missing-bank-account'))->bankBooks()->get('BNK');
            self::fail('Expected an UnexpectedResponseException.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame('GetBankBookResult/BankAccount is missing.', $exception->getMessage());
        }

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'GetBankBookResult/AutomaticBankProcessing/SignUpState is not one of Inactive, Pending, Active: "Unknown".',
        );

        $this->twinfield(ReplayClient::fixture('bankbook-get-invalid-sign-up-state'))->bankBooks()->get('BNK');
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('bankbook-fault-not-found', 500))->bankBooks()->get('NOPE');
            self::fail('Expected a BankBookNotFoundException.');
        } catch (BankBookNotFoundException $exception) {
            self::assertSame('NOPE', $exception->bankBookCode);
            self::assertSame('Code NOPE bestaat niet.', $exception->reason);
            self::assertInstanceOf(CommandQueryFaultException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('bankbook-fault-not-found', 500))->bankBooks()->find('NOPE'),
        );
    }

    public function testCreatesABankBookAndReadsIt(): void
    {
        $http = new ReplayClient(ReplayClient::response('bankbook-process'), ReplayClient::response('bankbook-get'));

        $bankBook = $this->twinfield($http)->bankBooks()->create(
            BankBookInput::create('ING', 'ING 3255112')
                ->shortName('ING')
                ->generalLedgerAccount('1100')
                ->bankAccount(
                    BankAccountInput::create()->name('Example B.V.')->iban('NL45 INGB 0003 2551 12')->country('nl'),
                ),
        );

        self::assertSame('ING', $bankBook->code);
        self::assertSame(
            '"http://www.twinfield.com/BankBookService/Process"',
            $http->requests[0]->getHeaderLine('SOAPAction'),
        );
        self::assertStringContainsString(
            'i:type="a:CreateBankBook"><a:Code>ING</a:Code>'
            . '<a:BankAccount><a:Bic i:nil="true"/><a:Country>NL</a:Country>'
            . '<a:Iban>NL45INGB0003255112</a:Iban><a:Name>Example B.V.</a:Name>'
            . '<a:Nbic i:nil="true"/><a:Number i:nil="true"/>'
            . '<a:ReferenceNumber i:nil="true"/></a:BankAccount><a:GeneralLedgerAccount>1100</a:GeneralLedgerAccount>'
            . '<a:Name>ING 3255112</a:Name><a:ShortName>ING</a:ShortName></Command>',
            $this->body($http->requests[0]),
        );
    }

    public function testCreateLeavesOutEmptyOptionalFields(): void
    {
        $http = new ReplayClient(ReplayClient::response('bankbook-process'), ReplayClient::response('bankbook-get'));

        $this->twinfield($http)->bankBooks()->create(BankBookInput::create('ING', 'ING'));

        self::assertStringContainsString(
            'i:type="a:CreateBankBook"><a:Code>ING</a:Code><a:Name>ING</a:Name></Command>',
            $this->body($http->requests[0]),
        );
    }

    public function testThrowsAlreadyExists(): void
    {
        $http = ReplayClient::fixture('bankbook-fault-already-exists', 500);

        $this->expectException(BankBookAlreadyExistsException::class);
        $this->expectExceptionMessage('Bank book ING already exists: Code ING bestaat al.');

        $this->twinfield($http)->bankBooks()->create(BankBookInput::create('ING', 'ING'));
    }

    public function testUpdateSendsOnlyChangedValues(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('bankbook-get'),
            ReplayClient::response('bankbook-process'),
            ReplayClient::response('bankbook-process'),
            ReplayClient::response('bankbook-get'),
        );
        $bankBooks = $this->twinfield($http)->bankBooks();
        $current = $this->twinfield(ReplayClient::fixture('bankbook-get'))->bankBooks()->get('ING');

        $bankBooks->update(
            $current->edit()
                ->name('ING zakelijk')
                ->bankAccount($current->bankAccount->edit()->bic(null)),
        );

        self::assertCount(4, $http->requests);
        self::assertStringContainsString(
            'i:type="a:ChangeBankBookName"><a:Code>ING</a:Code><a:Name>ING zakelijk</a:Name></Command>',
            $this->body($http->requests[1]),
        );
        self::assertStringContainsString(
            'i:type="a:ChangeBankBookBankAccount"><a:Code>ING</a:Code><a:BankAccount><a:Bic i:nil="true"/>'
            . '<a:Country>NL</a:Country><a:Iban>NL45INGB0003255112</a:Iban><a:Name>Example B.V.</a:Name>'
            . '<a:Nbic i:nil="true"/><a:Number>3255112</a:Number>'
            . '<a:ReferenceNumber i:nil="true"/></a:BankAccount></Command>',
            $this->body($http->requests[2]),
        );
    }

    public function testUpdateWithoutChangesSendsNoCommand(): void
    {
        $http = new ReplayClient(ReplayClient::response('bankbook-get'), ReplayClient::response('bankbook-get'));
        $bankBooks = $this->twinfield($http)->bankBooks();

        $current = $bankBooks->get('ING');
        $updated = $bankBooks->update($current->edit());

        self::assertEquals($current, $updated);
        self::assertCount(2, $http->requests, 'Two reads, no command.');
    }

    public function testUpdateThrowsNotFound(): void
    {
        $this->expectException(BankBookNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('bankbook-fault-not-found', 500))->bankBooks()
            ->update(BankBookInput::create('NOPE', 'Nope'));
    }

    public function testSendsSingleChangeCommands(): void
    {
        $http = new ReplayClient(...array_fill(0, 4, ReplayClient::response('bankbook-process')));
        $bankBooks = $this->twinfield($http)->bankBooks();

        $bankBooks->rename('ING', 'ING zakelijk');
        $bankBooks->changeShortName('ING', null);
        $bankBooks->changeGeneralLedgerAccount('ING', '1110');
        $bankBooks->changeBankAccount('ING', BankAccountInput::create()->number('3255112'));

        $bodies = array_map($this->body(...), $http->requests);

        self::assertStringContainsString(
            'i:type="a:ChangeBankBookName"><a:Code>ING</a:Code><a:Name>ING zakelijk</a:Name>',
            $bodies[0],
        );
        self::assertStringContainsString(
            'i:type="a:ChangeBankBookShortName"><a:Code>ING</a:Code><a:ShortName i:nil="true"/>',
            $bodies[1],
        );
        self::assertStringContainsString(
            'i:type="a:ChangeBankBookGeneralLedgerAccount"><a:Code>ING</a:Code>'
                . '<a:GeneralLedgerAccount>1110</a:GeneralLedgerAccount>',
            $bodies[2],
        );
        self::assertStringContainsString('<a:Number>3255112</a:Number>', $bodies[3]);
        self::assertStringContainsString('xmlns:a="' . self::DATA_NS . '"', $bodies[3]);
    }

    public function testKeepsOtherFaultsWithTheirCode(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('bankbook-fault-general-ledger-account-may-not-be-changed', 500))
                ->bankBooks()
                ->changeGeneralLedgerAccount('ING', '1110');
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('BankBookServiceFault', $exception->type);
            self::assertSame(
                BankBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged,
                BankBookServiceFaultCode::tryFrom($exception->errorCode),
            );
        }
    }

    public function testRequiresACompany(): void
    {
        $psr17 = new Psr17Factory();
        $twinfield = Twinfield::create(
            TestTokens::provider('https://example.test'),
            new ReplayClient(),
            $psr17,
            $psr17,
        );

        $this->expectException(LogicException::class);

        $twinfield->bankBooks()->get('ING');
    }

    private function body(RequestInterface $request): string
    {
        return (string) preg_replace('~>\s+<~', '><', (string) $request->getBody());
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
    }
}
