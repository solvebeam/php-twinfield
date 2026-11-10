<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\CashBook;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\CashBook\CashBook;
use SolveBeam\Twinfield\CashBook\CashBookAlreadyExistsException;
use SolveBeam\Twinfield\CashBook\CashBookInput;
use SolveBeam\Twinfield\CashBook\CashBookNotFoundException;
use SolveBeam\Twinfield\CashBook\CashBookServiceFaultCode;
use SolveBeam\Twinfield\CashBook\CashBookSummary;
use SolveBeam\Twinfield\Soap\CommandQueryFaultException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class CashBookResourceTest extends TestCase
{
    private const string DATA_NS = 'http://schemas.datacontract.org/2004/07/Twinfield.WebServices.CashBookService';

    public function testSearchesOnlyCashBooks(): void
    {
        $http = ReplayClient::fixture('finder-cash-books');

        $page = $this->twinfield($http)->cashBooks()->search('*')->inName()->limit(10)->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new CashBookSummary(TestTokens::company(), 'KAS', 'Kas'),
            new CashBookSummary(TestTokens::company(), 'VRK_1', 'Verkopen pin'),
        ], iterator_to_array($page));

        $body = $this->body($http->lastRequest());

        self::assertSame('https://example.test/webservices/finder.asmx', (string) $http->lastRequest()->getUri());
        self::assertStringContainsString('<type>BNK</type>', $body);
        self::assertStringContainsString('<field>2</field>', $body);
        self::assertStringContainsString('<string>banktype</string><string>1</string>', $body);
    }

    public function testGetsACashBook(): void
    {
        $http = ReplayClient::fixture('cashbook-get');

        $cashBook = $this->twinfield($http)->cashBooks()->get('KAS');

        self::assertEquals(new CashBook(
            company: TestTokens::company('1000'),
            code: 'KAS',
            name: 'Kas',
            shortName: 'Kas',
            generalLedgerAccount: '1000',
        ), $cashBook);

        $request = $http->lastRequest();

        self::assertSame('https://example.test/webservices/CashBookService.svc', (string) $request->getUri());
        self::assertSame('"http://www.twinfield.com/CashBookService/Query"', $request->getHeaderLine('SOAPAction'));
        self::assertStringContainsString('xmlns:a="' . self::DATA_NS . '"', $this->body($request));
        self::assertStringContainsString('i:type="a:GetCashBook"><a:Code>KAS</a:Code></Query>', $this->body($request));
    }

    public function testReadsEmptyValuesAsNull(): void
    {
        $cashBook = $this->twinfield(ReplayClient::fixture('cashbook-get-default'))->cashBooks()->get('KAS');

        self::assertNull($cashBook->shortName);
        self::assertNull($cashBook->generalLedgerAccount);
    }

    public function testThrowsOnAMissingName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('GetCashBookResult/Name is missing.');

        $this->twinfield(ReplayClient::fixture('cashbook-get-missing-name'))->cashBooks()->get('KAS');
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('cashbook-fault-not-found', 500))->cashBooks()->get('NOPE');
            self::fail('Expected a CashBookNotFoundException.');
        } catch (CashBookNotFoundException $exception) {
            self::assertSame('NOPE', $exception->cashBookCode);
            self::assertSame('Code NOPE bestaat niet.', $exception->reason);
            self::assertSame('Cash book NOPE not found: Code NOPE bestaat niet.', $exception->getMessage());
            self::assertInstanceOf(CommandQueryFaultException::class, $exception->getPrevious());
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('cashbook-fault-not-found', 500))->cashBooks()->find('NOPE'),
        );
    }

    public function testFindReturnsTheCashBook(): void
    {
        self::assertSame(
            'Kas',
            $this->twinfield(ReplayClient::fixture('cashbook-get'))->cashBooks()->find('KAS')?->name,
        );
    }

    public function testCreatesACashBookAndReadsIt(): void
    {
        $http = new ReplayClient(ReplayClient::response('cashbook-process'), ReplayClient::response('cashbook-get'));

        $cashBook = $this->twinfield($http)->cashBooks()->create(
            CashBookInput::create('KAS', 'Kas')->shortName('Kas')->generalLedgerAccount('1000'),
        );

        self::assertSame('KAS', $cashBook->code);
        self::assertCount(2, $http->requests);
        self::assertSame(
            '"http://www.twinfield.com/CashBookService/Process"',
            $http->requests[0]->getHeaderLine('SOAPAction'),
        );
        self::assertStringContainsString(
            'i:type="a:CreateCashBook"><a:Code>KAS</a:Code><a:GeneralLedgerAccount>1000</a:GeneralLedgerAccount>'
            . '<a:Name>Kas</a:Name><a:ShortName>Kas</a:ShortName></Command>',
            $this->body($http->requests[0]),
        );
    }

    public function testCreateLeavesOutEmptyOptionalFields(): void
    {
        $http = new ReplayClient(ReplayClient::response('cashbook-process'), ReplayClient::response('cashbook-get'));

        $this->twinfield($http)->cashBooks()->create(CashBookInput::create('KAS', 'Kas')->shortName(''));

        self::assertStringContainsString(
            'i:type="a:CreateCashBook"><a:Code>KAS</a:Code><a:Name>Kas</a:Name></Command>',
            $this->body($http->requests[0]),
        );
    }

    public function testThrowsAlreadyExists(): void
    {
        $http = ReplayClient::fixture('cashbook-fault-already-exists', 500);

        $this->expectException(CashBookAlreadyExistsException::class);
        $this->expectExceptionMessage('Cash book KAS already exists: Code KAS bestaat al.');

        $this->twinfield($http)->cashBooks()->create(CashBookInput::create('KAS', 'Kas'));
    }

    public function testUpdateSendsOnlyChangedValues(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('cashbook-get'),
            ReplayClient::response('cashbook-process'),
            ReplayClient::response('cashbook-process'),
            ReplayClient::response('cashbook-get'),
        );
        $current = $this->twinfield(ReplayClient::fixture('cashbook-get'))->cashBooks()->get('KAS');

        $this->twinfield($http)->cashBooks()->update($current->edit()->name('Kas winkel')->shortName(null));

        self::assertCount(4, $http->requests);
        self::assertStringContainsString(
            'i:type="a:ChangeCashBookName"><a:Code>KAS</a:Code><a:Name>Kas winkel</a:Name></Command>',
            $this->body($http->requests[1]),
        );
        self::assertStringContainsString(
            'i:type="a:ChangeCashBookShortName"><a:Code>KAS</a:Code><a:ShortName i:nil="true"/></Command>',
            $this->body($http->requests[2]),
        );
    }

    public function testUpdateWithoutChangesSendsNoCommand(): void
    {
        $http = new ReplayClient(ReplayClient::response('cashbook-get'), ReplayClient::response('cashbook-get'));
        $cashBooks = $this->twinfield($http)->cashBooks();

        $current = $cashBooks->get('KAS');
        $updated = $cashBooks->update($current->edit());

        self::assertEquals($current, $updated);
        self::assertCount(2, $http->requests, 'Two reads, no command.');
    }

    public function testUpdateThrowsNotFound(): void
    {
        $this->expectException(CashBookNotFoundException::class);

        $this->twinfield(ReplayClient::fixture('cashbook-fault-not-found', 500))->cashBooks()
            ->update(CashBookInput::create('NOPE', 'Nope'));
    }

    public function testSendsSingleChangeCommands(): void
    {
        $http = new ReplayClient(...array_fill(0, 4, ReplayClient::response('cashbook-process')));
        $cashBooks = $this->twinfield($http)->cashBooks();

        $cashBooks->rename('KAS', 'Kas winkel');
        $cashBooks->changeShortName('KAS', 'Winkel');
        $cashBooks->changeGeneralLedgerAccount('KAS', '1010');
        $cashBooks->changeGeneralLedgerAccount('KAS', '');

        $bodies = array_map($this->body(...), $http->requests);

        self::assertStringContainsString(
            'i:type="a:ChangeCashBookName"><a:Code>KAS</a:Code><a:Name>Kas winkel</a:Name>',
            $bodies[0],
        );
        self::assertStringContainsString(
            'i:type="a:ChangeCashBookShortName"><a:Code>KAS</a:Code><a:ShortName>Winkel</a:ShortName>',
            $bodies[1],
        );
        self::assertStringContainsString(
            'i:type="a:ChangeCashBookGeneralLedgerAccount"><a:Code>KAS</a:Code>'
                . '<a:GeneralLedgerAccount>1010</a:GeneralLedgerAccount>',
            $bodies[2],
        );
        self::assertStringContainsString(
            'i:type="a:ChangeCashBookGeneralLedgerAccount"><a:Code>KAS</a:Code><a:GeneralLedgerAccount i:nil="true"/>',
            $bodies[3],
        );
    }

    public function testRenameRequiresAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->twinfield(new ReplayClient())->cashBooks()->rename('KAS', '');
    }

    public function testKeepsOtherFaultsWithTheirCode(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('cashbook-fault-general-ledger-account-may-not-be-changed', 500))
                ->cashBooks()
                ->changeGeneralLedgerAccount('KAS', '1010');
            self::fail('Expected a CommandQueryFaultException.');
        } catch (CommandQueryFaultException $exception) {
            self::assertSame('CashBookServiceFault', $exception->type);
            self::assertSame(
                CashBookServiceFaultCode::GeneralLedgerAccountMayNotBeChanged,
                CashBookServiceFaultCode::tryFrom($exception->errorCode),
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

        $twinfield->cashBooks()->get('KAS');
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
