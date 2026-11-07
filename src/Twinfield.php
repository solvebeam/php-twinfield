<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Activity\ActivityResource;
use SolveBeam\Twinfield\BankBook\BankBookResource;
use SolveBeam\Twinfield\CashBook\CashBookResource;
use SolveBeam\Twinfield\BankTransaction\BankTransactionResource;
use SolveBeam\Twinfield\CashTransaction\CashTransactionResource;
use SolveBeam\Twinfield\Budget\BudgetResource;
use SolveBeam\Twinfield\AssetMethod\AssetMethodResource;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\CommandQuery\CommandQueryEnvelope;
use SolveBeam\Twinfield\CommandQuery\Query;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\CostCenter\CostCenterResource;
use SolveBeam\Twinfield\Finder\FinderQuery;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Office\OfficeResource;
use SolveBeam\Twinfield\Period\PeriodResource;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlString;
use SolveBeam\Twinfield\Soap\Psr17SoapRequestFactory;
use SolveBeam\Twinfield\Soap\SoapClient;
use SolveBeam\Twinfield\Soap\SoapResponse;

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
        $soap = new SoapClient(
            $httpClient ?? Psr18ClientDiscovery::find(),
            new Psr17SoapRequestFactory(
                $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
                $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            ),
        );

        return new self($accessTokenProvider, $soap, null);
    }

    /**
     * Client for another office (company code), e.g. `company('1000')`.
     */
    public function company(string $code): self
    {
        return new self($this->accessTokenProvider, $this->soap, $code);
    }

    /**
     * Client without company, e.g. to list all companies with ProcessXml.
     */
    public function withoutCompany(): self
    {
        return new self($this->accessTokenProvider, $this->soap, null);
    }

    public function companyCode(): ?string
    {
        return $this->companyCode;
    }

    public function offices(): OfficeResource
    {
        return new OfficeResource($this);
    }

    /**
     * Activities (dimension type ACT) of the company: search, read, create, update and delete.
     */
    public function activities(): ActivityResource
    {
        return new ActivityResource($this);
    }

    /**
     * Cost centers (dimension type KPL) of the company: search, read, create, update and delete.
     */
    public function costCenters(): CostCenterResource
    {
        return new CostCenterResource($this);
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
     * Asset methods of the company (how fixed assets are depreciated): search, read, create, update and delete.
     */
    public function assetMethods(): AssetMethodResource
    {
        return new AssetMethodResource($this);
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
