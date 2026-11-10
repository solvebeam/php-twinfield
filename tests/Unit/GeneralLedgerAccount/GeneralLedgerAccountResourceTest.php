<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Unit\GeneralLedgerAccount;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Dimension\DimensionAlreadyExistsException;
use SolveBeam\Twinfield\Dimension\DimensionNotFoundException;
use SolveBeam\Twinfield\GeneralLedgerAccount\ChildValidation;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountInput;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountSummary;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\GeneralLedgerAccount\SubAnalysis;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Tests\Support\Http\ReplayClient;
use SolveBeam\Twinfield\Tests\Support\TestTokens;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

final class GeneralLedgerAccountResourceTest extends TestCase
{
    public function testHasAResourcePerType(): void
    {
        $twinfield = $this->twinfield(new ReplayClient());

        self::assertSame(GeneralLedgerAccountType::BalanceSheet, $twinfield->balanceSheetAccounts()->type());
        self::assertSame(GeneralLedgerAccountType::ProfitAndLoss, $twinfield->profitAndLossAccounts()->type());
    }

    public function testSearchesProfitAndLossAccounts(): void
    {
        $http = ReplayClient::fixture('finder-general-ledger-accounts');

        $page = $this->twinfield($http)->profitAndLossAccounts()->search('80*')->inCode()->group('REV*')->get();

        self::assertSame(2, $page->totalRows);
        self::assertEquals([
            new GeneralLedgerAccountSummary(TestTokens::company(), '8000', 'Omzet'),
            new GeneralLedgerAccountSummary(TestTokens::company(), '8010', 'Omzet diensten'),
        ], iterator_to_array($page));

        $xpath = $this->soapXpath($http->lastRequest());

        self::assertSame('DIM', $xpath->evaluate('string(//tw:Search/tw:type)'));
        self::assertSame('80*', $xpath->evaluate('string(//tw:Search/tw:pattern)'));
        self::assertSame('1', $xpath->evaluate('string(//tw:Search/tw:field)'));
        self::assertSame(['dimtype' => 'PNL', 'group' => 'REV*'], $this->finderOptions($xpath));
    }

    public function testSearchesBalanceSheetAccountsIncludingHidden(): void
    {
        $http = ReplayClient::fixture('finder-general-ledger-accounts-hidden');

        $accounts = iterator_to_array(
            $this->twinfield($http)->balanceSheetAccounts()->search()->includeHidden()->all(),
            false,
        );

        self::assertEquals([
            new GeneralLedgerAccountSummary(TestTokens::company(), '8000', 'Omzet', hidden: false),
            new GeneralLedgerAccountSummary(TestTokens::company(), '8090', 'Omzet oud', hidden: true),
        ], $accounts);
        self::assertSame(
            ['dimtype' => 'BAS', 'includehidden' => '1'],
            $this->finderOptions($this->soapXpath($http->lastRequest())),
        );
    }

    public function testThrowsOnASearchRowWithoutName(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Finder general ledger account row 3 has no code or name: ["8000",""].');

        GeneralLedgerAccountSummary::fromFinderRow(['8000', ''], 3, TestTokens::company());
    }

    public function testThrowsOnAnUnknownSearchStatus(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage(
            'Finder general ledger account row 0 has status "deleted", expected active or hidden.',
        );

        GeneralLedgerAccountSummary::fromFinderRow(['8000', 'Omzet', 'deleted'], 0, TestTokens::company());
    }

    public function testReadsAnAccount(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-read-profit-and-loss');

        $account = $this->twinfield($http)->profitAndLossAccounts()->get('8000');

        self::assertSame(
            '<read><type>dimensions</type><office>1000</office><dimtype>PNL</dimtype><code>8000</code></read>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('8000', $account->code);
        self::assertSame('Omzet', $account->name);
    }

    public function testThrowsNotFound(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('general-ledger-account-read-not-found'))
                ->profitAndLossAccounts()
                ->get('9999');
            self::fail('Expected a DimensionNotFoundException.');
        } catch (DimensionNotFoundException $exception) {
            self::assertSame('PNL', $exception->dimensionType);
            self::assertSame('9999', $exception->dimensionCode);
            self::assertSame('Dimensie 9999 bestaat niet.', $exception->reason);
        }
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        self::assertNull(
            $this->twinfield(ReplayClient::fixture('general-ledger-account-read-not-found'))
                ->profitAndLossAccounts()
                ->find('9999'),
        );
    }

    public function testSavesAnAccount(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-save');

        $account = $this->twinfield($http)->profitAndLossAccounts()->save(
            GeneralLedgerAccountInput::profitAndLoss('8010', 'Omzet diensten')
                ->subAnalysis(SubAnalysis::Required)
                ->childValidation(ChildValidation::dimensionType('KPL')),
        );

        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8010</code><name>Omzet diensten</name>'
                . '<financials><subanalyse>true</subanalyse><childvalidations>'
                . '<childvalidation type="type" level="2">KPL</childvalidation>'
                . '</childvalidations></financials></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
        self::assertSame('8010', $account->code);
        self::assertSame(SubAnalysis::Required, $account->subAnalysis);
    }

    public function testThrowsTheTwinfieldErrorsWhenSavingFails(): void
    {
        try {
            $this->twinfield(ReplayClient::fixture('general-ledger-account-save-error'))->profitAndLossAccounts()->save(
                GeneralLedgerAccountInput::profitAndLoss('REVENUE', 'Omzet'),
            );
            self::fail('Expected a ProcessXmlException.');
        } catch (ProcessXmlException $exception) {
            self::assertSame(
                'dimension/code: Code voldoet niet aan het formaat [4-9][0-9][0-9][0-9].',
                $exception->getMessage(),
            );
        }
    }

    public function testRefusesAnInputOfTheOtherType(): void
    {
        $http = new ReplayClient();

        try {
            $this->twinfield($http)->balanceSheetAccounts()->save(
                GeneralLedgerAccountInput::profitAndLoss('8010', 'Omzet'),
            );
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Account 8010 is a profit and loss account (PNL), but '
                    . 'this resource is for balance sheet accounts (BAS).',
                $exception->getMessage(),
            );
        }

        self::assertCount(0, $http->requests, 'Nothing is sent.');
    }

    public function testCreatesAfterCheckingTheCodeIsFree(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('general-ledger-account-read-not-found'),
            ReplayClient::response('general-ledger-account-save'),
        );

        $account = $this->twinfield($http)->profitAndLossAccounts()->create(
            GeneralLedgerAccountInput::profitAndLoss('8010', 'Omzet diensten'),
        );

        self::assertSame('8010', $account->code);
        self::assertCount(2, $http->requests);
        self::assertStringStartsWith('<read>', $this->xmlRequest($http->requests[0]));
        self::assertStringStartsWith('<dimension>', $this->xmlRequest($http->requests[1]));
    }

    public function testCreateRefusesAnExistingCode(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-read-profit-and-loss');

        try {
            $this->twinfield($http)->profitAndLossAccounts()->create(
                GeneralLedgerAccountInput::profitAndLoss('8000', 'Omzet'),
            );
            self::fail('Expected a DimensionAlreadyExistsException.');
        } catch (DimensionAlreadyExistsException $exception) {
            self::assertSame('Dimension PNL 8000 already exists.', $exception->getMessage());
        }

        self::assertCount(1, $http->requests, 'Nothing is saved.');
    }

    public function testUpdatesAnExistingAccount(): void
    {
        $http = new ReplayClient(
            ReplayClient::response('general-ledger-account-read-profit-and-loss'),
            ReplayClient::response('general-ledger-account-read-profit-and-loss'),
            ReplayClient::response('general-ledger-account-save'),
        );
        $accounts = $this->twinfield($http)->profitAndLossAccounts();

        $accounts->update($accounts->get('8000')->edit()->name('Omzet producten'));

        self::assertCount(3, $http->requests);
        self::assertSame(
            '<dimension><office>1000</office><type>PNL</type><code>8000</code><name>Omzet producten</name>'
                . '<shortname/><beginperiod>0</beginperiod><beginyear>0</beginyear>'
                . '<endperiod>0</endperiod><endyear>0</endyear>'
                . '<financials><matchtype>notmatchable</matchtype>'
                . '<subanalyse>maybe</subanalyse><vatcode fixed="false"/>'
                . '<childvalidations><childvalidation type="type" level="2">KPL</childvalidation></childvalidations>'
                . '</financials></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testUpdateRefusesAMissingAccount(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-read-not-found');

        $this->expectException(DimensionNotFoundException::class);

        try {
            $this->twinfield($http)->profitAndLossAccounts()->update(
                GeneralLedgerAccountInput::profitAndLoss('9999', 'Omzet'),
            );
        } finally {
            self::assertCount(1, $http->requests, 'Nothing is saved.');
        }
    }

    public function testDeletes(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-delete');

        self::assertSame(DeleteResult::Deleted, $this->twinfield($http)->profitAndLossAccounts()->delete('8010'));
        self::assertSame(
            '<dimension status="deleted"><office>1000</office><type>PNL</type><code>8010</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
    }

    public function testDeleteHidesAnAccountInUse(): void
    {
        self::assertSame(
            DeleteResult::Hidden,
            $this->twinfield(ReplayClient::fixture('general-ledger-account-delete-hidden'))
                ->profitAndLossAccounts()
                ->delete('8000'),
        );
    }

    public function testDeleteThrowsWhenTheAccountStaysActive(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Profit and loss account 8000 is still active after deleting it.');

        $this->twinfield(ReplayClient::fixture('general-ledger-account-activate'))->profitAndLossAccounts()->delete(
            '8000',
        );
    }

    public function testActivates(): void
    {
        $http = ReplayClient::fixture('general-ledger-account-activate');

        $this->twinfield($http)->profitAndLossAccounts()->activate('8000');

        self::assertSame(
            '<dimension status="active"><office>1000</office><type>PNL</type><code>8000</code></dimension>',
            $this->xmlRequest($http->lastRequest()),
        );
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
        $this->expectExceptionMessage('Balance sheet accounts belong to a company: use company() first.');

        $twinfield->balanceSheetAccounts()->get('1310');
    }

    private function twinfield(ReplayClient $http): Twinfield
    {
        $psr17 = new Psr17Factory();

        return Twinfield::create(TestTokens::provider('https://example.test'), $http, $psr17, $psr17)->company('1000');
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

    /**
     * @return array<string, string>
     */
    private function finderOptions(DOMXPath $xpath): array
    {
        $options = [];
        $nodes = $xpath->query('//tw:Search/tw:options/tw:ArrayOfString');

        foreach ($nodes === false ? [] : $nodes as $option) {
            self::assertInstanceOf(\DOMElement::class, $option);

            $name = $xpath->evaluate('string(tw:string[1])', $option);
            $value = $xpath->evaluate('string(tw:string[2])', $option);

            self::assertIsString($name);
            self::assertIsString($value);

            $options[$name] = $value;
        }

        return $options;
    }
}
