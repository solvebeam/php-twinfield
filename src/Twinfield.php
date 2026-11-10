<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use BackedEnum;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Activity\ActivityResource;
use SolveBeam\Twinfield\BankBook\BankBookResource;
use SolveBeam\Twinfield\BankFeed\BankFeedResource;
use SolveBeam\Twinfield\CashBook\CashBookResource;
use SolveBeam\Twinfield\BankTransaction\BankTransactionResource;
use SolveBeam\Twinfield\CashTransaction\CashTransactionResource;
use SolveBeam\Twinfield\Budget\BudgetResource;
use SolveBeam\Twinfield\BrowseData\BrowseCode;
use SolveBeam\Twinfield\BrowseData\BrowseDefinitionResource;
use SolveBeam\Twinfield\BrowseData\BrowseQuery;
use SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod\AssetMethodResource;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\CommandQueryEnvelope;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\CostCenter\CostCenterResource;
use SolveBeam\Twinfield\Currency\CurrencyResource;
use SolveBeam\Twinfield\Customer\CustomerResource;
use SolveBeam\Twinfield\Declaration\DeclarationResource;
use SolveBeam\Twinfield\DeletedTransaction\DeletedTransactionResource;
use SolveBeam\Twinfield\DimensionGroup\DimensionGroupResource;
use SolveBeam\Twinfield\DimensionType\DimensionTypeResource;
use SolveBeam\Twinfield\ElectronicBankStatement\ElectronicBankStatementResource;
use SolveBeam\Twinfield\Finder\FinderQuery;
use SolveBeam\Twinfield\ClassicFixedAsset\ClassicFixedAssetResource;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountResource;
use SolveBeam\Twinfield\GeneralLedgerAccount\GeneralLedgerAccountType;
use SolveBeam\Twinfield\Hierarchy\HierarchyResource;
use SolveBeam\Twinfield\LedgerAccount\LedgerAccountResource;
use SolveBeam\Twinfield\Matching\MatchingResource;
use SolveBeam\Twinfield\PayAndCollect\PayAndCollectResource;
use SolveBeam\Twinfield\JournalTransaction\JournalTransactionResource;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\FixedAsset\FixedAssetResource;
use SolveBeam\Twinfield\Office\Office;
use SolveBeam\Twinfield\Office\OfficeResource;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\Period\PeriodResource;
use SolveBeam\Twinfield\PostingRule\PostingRuleResource;
use SolveBeam\Twinfield\Project\ProjectResource;
use SolveBeam\Twinfield\PurchaseTransaction\PurchaseTransactionResource;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlString;
use SolveBeam\Twinfield\Rate\RateResource;
use SolveBeam\Twinfield\Rest\CompanyIds;
use SolveBeam\Twinfield\Rest\JsonWriter;
use SolveBeam\Twinfield\Rest\RawBody;
use SolveBeam\Twinfield\Rest\RestClient;
use SolveBeam\Twinfield\Rest\RestResponse;
use SolveBeam\Twinfield\ReversalTransaction\ReversalTransactionResource;
use SolveBeam\Twinfield\SalesArticle\SalesArticleResource;
use SolveBeam\Twinfield\SalesInvoice\SalesInvoiceResource;
use SolveBeam\Twinfield\SalesTransaction\SalesTransactionResource;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
use SolveBeam\Twinfield\Soap\SoapClient;
use SolveBeam\Twinfield\Soap\SoapResponse;
use SolveBeam\Twinfield\Supplier\SupplierResource;
use SolveBeam\Twinfield\TransactionBlockedValue\TransactionBlockedValueResource;
use SolveBeam\Twinfield\User\UserResource;
use SolveBeam\Twinfield\VatCode\VatCodeResource;

/**
 * Entry point of the SDK.
 *
 *   $twinfield = Twinfield::create($accessTokenProvider)->company('1000');
 *
 *   foreach ($twinfield->offices()->list() as $office) { … }
 *
 * Instances are immutable: company() returns a new client.
 */
final readonly class Twinfield
{
    private function __construct(
        private AccessTokenProvider $accessTokenProvider,
        private SoapClient $soap,
        private RestClient $rest,
        private CompanyIds $companyIds,
        private ?string $companyCode,
    ) {
    }

    /**
     * Without HTTP objects, installed PSR-18/PSR-17 implementations are discovered automatically.
     */
    public static function create(
        AccessTokenProvider $accessTokenProvider,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $httpClient ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        $soap = new SoapClient($httpClient, new Psr17SoapRequestFactory($requestFactory, $streamFactory));
        $rest = new RestClient($httpClient, $requestFactory, $streamFactory);

        return new self($accessTokenProvider, $soap, $rest, new CompanyIds(), null);
    }

    /**
     * Client for another office (company), e.g. `company('1000')`, an office from
     * `offices()->list()` or `offices()->get()`, or the `company` of a model.
     *
     * The REST API needs the GUID of the company. An office from `offices()->list()` brings it along;
     * otherwise the SDK looks it up once with the Finder, unless you pass it, e.g. from a shadow
     * database: `company('1000', 'ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9')`.
     *
     * @param ?string $companyId GUID of the company, see companyId().
     *
     * @throws InvalidArgumentException When an office or company reference belongs to another
     *                                  organisation than the access token, or the company ID is
     *                                  not a GUID.
     */
    public function company(string|CompanyReference|Office|OfficeSummary $company, ?string $companyId = null): self
    {
        if ($company instanceof OfficeSummary) {
            $companyId ??= $company->companyId;
        }

        if (is_string($company)) {
            $code = $company;
        } else {
            $reference = $company instanceof CompanyReference ? $company : $company->reference();
            $organisation = $this->organisation();

            if (!$reference->organisation->equals($organisation)) {
                throw new InvalidArgumentException(sprintf(
                    'Company %s belongs to organisation %s, but the access token to organisation %s.',
                    $reference->code,
                    $reference->organisation->code,
                    $organisation->code,
                ));
            }

            $code = $reference->code;
        }

        if ($code === '') {
            throw new InvalidArgumentException('Company code must not be empty.');
        }

        if ($companyId !== null) {
            $this->companyIds->set($code, $companyId);
        }

        return new self($this->accessTokenProvider, $this->soap, $this->rest, $this->companyIds, $code);
    }

    /**
     * Client without company, e.g. to list all companies with ProcessXml.
     */
    public function withoutCompany(): self
    {
        return new self($this->accessTokenProvider, $this->soap, $this->rest, $this->companyIds, null);
    }

    public function companyCode(): ?string
    {
        return $this->companyCode;
    }

    /**
     * The company of the client, with its organisation.
     *
     * @throws LogicException When the client has no company.
     */
    public function companyReference(): CompanyReference
    {
        if ($this->companyCode === null) {
            throw new LogicException('The client has no company: use company() first.');
        }

        return new CompanyReference($this->organisation(), $this->companyCode);
    }

    /**
     * GUID of the company, e.g. `ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9`: the REST API identifies a
     * company by this GUID, not by its code. Twinfield only returns it with the Finder (`OFF` with
     * option `includeid`), so without a GUID from company() the SDK lists the offices once
     * (`offices()->list()`) and remembers the GUIDs of all companies.
     *
     * @throws LogicException When the client has no company, or the user has no access to it.
     */
    public function companyId(): string
    {
        if ($this->companyCode === null) {
            throw new LogicException('The client has no company: use company() first.');
        }

        $id = $this->companyIds->get($this->companyCode);

        if ($id !== null) {
            return $id;
        }

        foreach ($this->offices()->list() as $office) {
            $this->companyIds->set($office->code, $office->companyId);
        }

        return $this->companyIds->get($this->companyCode) ?? throw new LogicException(sprintf(
            'Company %s is not in the offices of the user, so its GUID is unknown.',
            $this->companyCode,
        ));
    }

    /**
     * Organisation the access token belongs to. Company and user codes are only unique within an
     * organisation; every model the SDK reads carries it.
     *
     * @see AccessTokenProvider::organisation()
     */
    public function organisation(): Organisation
    {
        return $this->accessTokenProvider->organisation();
    }

    /**
     * Code of the Twinfield user the access token belongs to, e.g. `JDOE`. Read the user with
     * `users()->current()`.
     *
     * @see AccessTokenProvider::userCode()
     */
    public function userCode(): string
    {
        return $this->accessTokenProvider->userCode();
    }

    /**
     * Offices (companies) the user has access to: list, search and read their settings.
     */
    public function offices(): OfficeResource
    {
        return new OfficeResource($this);
    }

    /**
     * Users of the organisation: search, read (also the user of the access token), create,
     * update and delete. Needs a company in the client, any company works.
     */
    public function users(): UserResource
    {
        return new UserResource($this);
    }

    /**
     * Activities (dimension type ACT) of the company: search, read, create, update and delete.
     */
    public function activities(): ActivityResource
    {
        return new ActivityResource($this);
    }

    /**
     * Projects (dimension type PRJ) of the company: search, read, create, update and delete.
     */
    public function projects(): ProjectResource
    {
        return new ProjectResource($this);
    }

    /**
     * Rates of Time & Expenses (hours and quantities such as kilometres) of the company:
     * search, read, create, update and delete.
     */
    public function rates(): RateResource
    {
        return new RateResource($this);
    }

    /**
     * VAT codes of the company, with their percentages over time and the accounts the VAT is
     * posted on: search, read, create, update and delete.
     */
    public function vatCodes(): VatCodeResource
    {
        return new VatCodeResource($this);
    }

    /**
     * Cost centers (dimension type KPL) of the company: search, read, create, update and delete.
     */
    public function costCenters(): CostCenterResource
    {
        return new CostCenterResource($this);
    }

    /**
     * Customers (dimension type DEB) of the company: search, read, create, update and delete.
     */
    public function customers(): CustomerResource
    {
        return new CustomerResource($this);
    }

    /**
     * Suppliers (dimension type CRD) of the company: search, read, create, update and delete.
     */
    public function suppliers(): SupplierResource
    {
        return new SupplierResource($this);
    }

    /**
     * Currencies of the company and their exchange rates: search, read, create, update and delete.
     */
    public function currencies(): CurrencyResource
    {
        return new CurrencyResource($this);
    }

    /**
     * Dimension groups of the company (sets of dimensions): search, read, create, update and delete.
     */
    public function dimensionGroups(): DimensionGroupResource
    {
        return new DimensionGroupResource($this);
    }

    /**
     * Dimension types of the company (BAS, DEB, KPL, …): search, read and update the name,
     * code mask and address labels.
     */
    public function dimensionTypes(): DimensionTypeResource
    {
        return new DimensionTypeResource($this);
    }

    /**
     * Fixed assets of the new fixed assets module (REST API): assets, classes, groups and the
     * reconciliation report. Read-only. For the classic module use `classicFixedAssets()`.
     */
    public function fixedAssets(): FixedAssetResource
    {
        return new FixedAssetResource($this);
    }

    /**
     * Fixed assets of the classic fixed assets module (dimension type AST): search, read, create,
     * update and delete. Twinfield is phasing this module out; the new module is `fixedAssets()`.
     */
    public function classicFixedAssets(): ClassicFixedAssetResource
    {
        return new ClassicFixedAssetResource($this);
    }

    /**
     * Balance sheet accounts (dimension type BAS) of the company: search, read, create, update
     * and delete.
     */
    public function balanceSheetAccounts(): GeneralLedgerAccountResource
    {
        return new GeneralLedgerAccountResource($this, GeneralLedgerAccountType::BalanceSheet);
    }

    /**
     * Profit and loss accounts (dimension type PNL) of the company: search, read, create, update
     * and delete.
     */
    public function profitAndLossAccounts(): GeneralLedgerAccountResource
    {
        return new GeneralLedgerAccountResource($this, GeneralLedgerAccountType::ProfitAndLoss);
    }

    /**
     * Ledger accounts of the company on the REST API: general ledger accounts and the other
     * dimensions with their GUIDs. Read-only.
     */
    public function ledgerAccounts(): LedgerAccountResource
    {
        return new LedgerAccountResource($this);
    }

    /**
     * Sales articles of the company (REST API): search, read, create, update, delete, import and
     * export.
     */
    public function salesArticles(): SalesArticleResource
    {
        return new SalesArticleResource($this);
    }

    /**
     * Posting rules of the customers and suppliers of the company (REST API). Read-only.
     */
    public function postingRules(): PostingRuleResource
    {
        return new PostingRuleResource($this);
    }

    /**
     * Bank feeds of the company: PSD2 feeds (REST API). Read-only.
     */
    public function bankFeeds(): BankFeedResource
    {
        return new BankFeedResource($this);
    }

    /**
     * Bank books of the company (BankBookService): search, read, create and update.
     */
    public function bankBooks(): BankBookResource
    {
        return new BankBookResource($this);
    }

    /**
     * Budgets of the company (BudgetService): search, and budgeted versus actual amounts. Read-only.
     */
    public function budgets(): BudgetResource
    {
        return new BudgetResource($this);
    }

    /**
     * Cash books of the company (CashBookService): search, read, create and update.
     */
    public function cashBooks(): CashBookResource
    {
        return new CashBookResource($this);
    }

    /**
     * Bank transactions of the company: read, create, update, post and delete.
     */
    public function bankTransactions(): BankTransactionResource
    {
        return new BankTransactionResource($this);
    }

    /**
     * Cash transactions of the company: read, create, update, post and delete.
     */
    public function cashTransactions(): CashTransactionResource
    {
        return new CashTransactionResource($this);
    }

    /**
     * Sales transactions of the company: read, create, update, post, delete, change the pay
     * status and search open invoices.
     */
    public function salesTransactions(): SalesTransactionResource
    {
        return new SalesTransactionResource($this);
    }

    /**
     * Purchase transactions of the company: read, create, update, post, delete, change the pay
     * status and search open invoices.
     */
    public function purchaseTransactions(): PurchaseTransactionResource
    {
        return new PurchaseTransactionResource($this);
    }

    /**
     * Journal transactions of the company: read, create, update, post and delete.
     */
    public function journalTransactions(): JournalTransactionResource
    {
        return new JournalTransactionResource($this);
    }

    /**
     * Reversals of journal transactions of the company: read, reverse a final transaction, and
     * set or undo the reversal of a provisional one.
     */
    public function reversalTransactions(): ReversalTransactionResource
    {
        return new ReversalTransactionResource($this);
    }

    /**
     * Sales invoices of the company (Classic invoicing module): read, create, update,
     * calculate, make final and search invoice types.
     */
    public function salesInvoices(): SalesInvoiceResource
    {
        return new SalesInvoiceResource($this);
    }

    /**
     * Deleted transactions of the company (DeletedTransactionsService), of all day books. Read-only.
     */
    public function deletedTransactions(): DeletedTransactionResource
    {
        return new DeletedTransactionResource($this);
    }

    /**
     * Electronic bank statements of the company: import bank statements and let Twinfield
     * allocate the lines, and read statements with their allocations (BankStatementService).
     * A statement can't be changed.
     */
    public function electronicBankStatements(): ElectronicBankStatementResource
    {
        return new ElectronicBankStatementResource($this);
    }

    /**
     * VAT, ICT and tax group returns (declarations.asmx): list them, download them as XBRL or
     * XML, get the payment reference and set their status.
     */
    public function declarations(): DeclarationResource
    {
        return new DeclarationResource($this);
    }

    /**
     * Reporting structures (hierarchies.asmx): search, read with all nodes and the accounts of the
     * company, save, bind and unbind accounts, hide and delete.
     */
    public function hierarchies(): HierarchyResource
    {
        return new HierarchyResource($this);
    }

    /**
     * Matching (afletteren): match existing transaction lines with each other, e.g. a sales
     * invoice and its payment (XML web services), and undo a match (matching.asmx).
     */
    public function matching(): MatchingResource
    {
        return new MatchingResource($this);
    }

    /**
     * Pay & Collect (payandcollect.asmx): payment runs and direct debit runs, from selecting the
     * open invoices to the SEPA file.
     */
    public function payAndCollect(): PayAndCollectResource
    {
        return new PayAndCollectResource($this);
    }

    /**
     * Blocked values of transaction lines (TransactionBlockedValueService): register and unregister.
     */
    public function transactionBlockedValues(): TransactionBlockedValueResource
    {
        return new TransactionBlockedValueResource($this);
    }

    /**
     * Asset methods of the classic fixed assets module (how fixed assets are depreciated): search,
     * read, create, update and delete.
     */
    public function classicAssetMethods(): AssetMethodResource
    {
        return new AssetMethodResource($this);
    }

    /**
     * Query on browse data: transaction lines, balances, open items, … of the company.
     *
     *   $result = $twinfield->company('1000')->browse(BrowseCode::GeneralLedgerDetailsV3)
     *       ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.valuesigned')
     *       ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
     *       ->equal('fin.trs.head.status', 'normal')
     *       ->get();
     *
     * @param BrowseCode|string $code A string for codes the SDK does not know yet.
     */
    public function browse(BrowseCode|string $code): BrowseQuery
    {
        return new BrowseQuery($this, $code instanceof BrowseCode ? $code->value : $code);
    }

    /**
     * Browse definitions (the fields of a browse code) and browse fields, to design a browse query.
     */
    public function browseDefinitions(): BrowseDefinitionResource
    {
        return new BrowseDefinitionResource($this);
    }

    public function finder(FinderType $type): FinderQuery
    {
        return new FinderQuery($this, $type);
    }

    /**
     * Years and periods of the company (PeriodService).
     */
    public function periods(): PeriodResource
    {
        return new PeriodResource($this);
    }

    /**
     * SOAP endpoint on the organisation's cluster, e.g. `finder` →
     * `https://api.accounting.twinfield.com/webservices/finder.asmx`, or
     * `endpoint('PeriodService', 'svc')` for a Command & Query web service.
     */
    public function endpoint(string $service, string $extension = 'asmx'): string
    {
        $clusterUrl = rtrim($this->accessTokenProvider->clusterUrl(), '/');

        return sprintf('%s/webservices/%s.%s', $clusterUrl, $service, $extension);
    }

    /**
     * Low-level request on the REST API with authentication; prefer the resource methods.
     *
     *   $vatCodes = $twinfield->company('1000')
     *       ->rest('GET', '/organisations/{organisationId}/companies/{companyId}/vat/vatcodes', ['limit' => 10])
     *       ->json()
     *       ->list('items');
     *
     * Copy the path from the Swagger definition: `{organisationId}` and `{companyId}` are replaced by
     * the GUIDs of the organisation and company. The REST API needs a scope per operation, e.g.
     * `readVat`; the Swagger description of each operation lists them.
     *
     * @param array<string, scalar|BackedEnum|list<scalar|BackedEnum>|null> $query Null values are left
     *        out; a list repeats the parameter (`vats=a&vats=b`); booleans become `true`/`false`.
     * @param array<array-key, mixed>|object|null $body Encoded with JsonWriter (amounts as exact numbers),
     *        or a RawBody that is sent as is (a file upload, a JSON Patch).
     * @param array<string, string> $headers E.g. `['If-Match' => '3']`.
     *
     * @see https://api.accounting.twinfield.com/Api/swagger/ui/index
     * @see RestClient::send() for the exceptions.
     */
    public function rest(
        string $method,
        string $path,
        array $query = [],
        array|object|null $body = null,
        array $headers = [],
    ): RestResponse {
        $replacements = ['{organisationId}' => $this->organisation()->id];

        if (str_contains($path, '{companyId}')) {
            $replacements['{companyId}'] = $this->companyId();
        }

        $resolved = strtr($path, $replacements);

        if (preg_match('~\{[^}]*\}~', $resolved, $matches) === 1) {
            throw new InvalidArgumentException(sprintf('Path %s has an unknown placeholder %s.', $path, $matches[0]));
        }

        $url = rtrim($this->accessTokenProvider->clusterUrl(), '/') . '/Api' . $resolved;
        $queryString = self::queryString($query);

        if ($queryString !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        if ($body instanceof RawBody) {
            $headers += ['Content-Type' => $body->contentType];
        }

        return $this->rest->send(
            $method,
            $url,
            $this->accessTokenProvider->accessToken(),
            $headers,
            match (true) {
                $body === null => null,
                $body instanceof RawBody => $body->contents,
                default => JsonWriter::encode($body),
            },
            preg_replace('~^/organisations/\{organisationId\}/companies/\{companyId\}~', '', $path) ?? $path,
        );
    }

    /**
     * @param array<string, scalar|BackedEnum|list<scalar|BackedEnum>|null> $query
     */
    private static function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $item = $item instanceof BackedEnum ? $item->value : $item;
                $item = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;

                $pairs[] = rawurlencode($name) . '=' . rawurlencode($item);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * Low-level SOAP call with authentication; prefer the resource methods.
     */
    public function call(string $service, object $body): SoapResponse
    {
        $header = new AuthenticationHeader(
            accessToken: $this->accessTokenProvider->accessToken(),
            companyCode: $this->companyCode,
        );

        return $this->soap->send($this->endpoint($service), $body, $header);
    }

    /**
     * Request on the XML web services (ProcessXmlString on `processxml.asmx`).
     *
     *   $result = $twinfield->processXml('<read><type>dimensions</type><office>1000</office><dimtype>DEB</dimtype><code>1000</code></read>');
     *   $name = $result->xpath()->evaluate('string(/dimension/name)');
     *
     * The request is an XML string: use `XmlBuilder::toXml()`, or e.g. `saveXML()` (DOM),
     * `asXML()` (SimpleXML) or `outputMemory()` (XMLWriter). Comments and whitespace
     * between elements are removed before sending.
     *
     * @throws ProcessXmlException When Twinfield did not process (all of) the request (`result="0"`).
     *
     * @see https://developers.twinfield.com/documentation/api/types/xml-web-services/
     */
    public function processXml(string $request): ProcessXmlResult
    {
        $response = $this->call('processxml', ProcessXmlString::fromXml($request));

        return ProcessXmlResult::fromSoapResponse($response)->throwIfFailed();
    }

    /**
     * Low-level query on a Command & Query web service; prefer the resource methods.
     *
     *   $result = $twinfield->query('PeriodService', new Query('GetYears'), 'GetYearsResult');
     *   $years = $result->ints('Years');
     *
     * @param string $resultType Expected `i:type` of the result, e.g. `GetYearsResult`.
     *
     * @see https://developers.twinfield.com/documentation/api/types/command-and-query-webservices
     */
    public function query(string $service, Query $query, string $resultType): ResultElement
    {
        $envelope = CommandQueryEnvelope::query($service, $query, $this->commandQueryAuthentication());

        $response = $this->soap->sendEnvelope($this->endpoint($service, 'svc'), $envelope);

        return ResultElement::fromQueryResponse($response, $resultType);
    }

    /**
     * Low-level command on a Command & Query web service; prefer the resource methods.
     * Commands change data and return nothing; errors are thrown as SoapFaultException.
     *
     *   $twinfield->process('PeriodService', new Command('ClosePeriod', ['PeriodNumber' => 1, 'Year' => 2021]));
     */
    public function process(string $service, Command $command): void
    {
        $envelope = CommandQueryEnvelope::command($service, $command, $this->commandQueryAuthentication());

        $this->soap->sendEnvelope($this->endpoint($service, 'svc'), $envelope);
    }

    /**
     * Command & Query web services always work on one company.
     */
    private function commandQueryAuthentication(): AuthenticationHeader
    {
        if ($this->companyCode === null) {
            throw new LogicException('Command & Query web services need a company: use company() first.');
        }

        return new AuthenticationHeader(
            accessToken: $this->accessTokenProvider->accessToken(),
            companyCode: $this->companyCode,
        );
    }
}
