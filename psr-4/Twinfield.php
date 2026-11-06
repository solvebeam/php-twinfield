<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SolveBeam\Twinfield\Authentication\AccessTokenProvider;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Finder\FinderQuery;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\Office\OfficeResource;
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

    public function companyCode(): ?string
    {
        return $this->companyCode;
    }

    public function offices(): OfficeResource
    {
        return new OfficeResource($this);
    }

    public function finder(FinderType $type): FinderQuery
    {
        return new FinderQuery($this, $type);
    }

    /**
     * SOAP endpoint on the organisation's cluster, e.g. `finder` →
     * `https://api.accounting.twinfield.com/webservices/finder.asmx`.
     */
    public function endpoint(string $service): string
    {
        return sprintf('%s/webservices/%s.asmx', rtrim($this->accessTokenProvider->clusterUrl(), '/'), $service);
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
}
