<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use SolveBeam\Twinfield\Authentication\AuthenticationHeader;
use SolveBeam\Twinfield\Finder\Search;
use SolveBeam\Twinfield\Finder\SearchResponse;
use SolveBeam\Twinfield\Soap\SoapClient;
use SolveBeam\Twinfield\Soap\SoapEnvelopeBuilder;
use SolveBeam\Twinfield\Soap\SoapFaultException;
use SolveBeam\Twinfield\Soap\SoapRequestFactory;

final class DiscoverySoapRequestFactory implements SoapRequestFactory
{
    public function createRequest(string $method, string $url): RequestInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory()->createRequest($method, $url);
    }

    public function createStream(string $body): StreamInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory()->createStream($body);
    }
}

$accessToken = (string) (getenv('TWINFIELD_ACCESS_TOKEN') ?: '55b80a23746edabe309c309d85311857');
$companyCode = (string) (getenv('TWINFIELD_COMPANY_CODE') ?: '1000');
$endpoint = (string) (getenv('TWINFIELD_SOAP_ENDPOINT') ?: 'https://api.accounting.twinfield.com/webservices/finder.asmx');

$argv = $_SERVER['argv'] ?? [];
$executeRemote = isset($_GET['send']) || in_array('--send', $argv, true);

$builder = SoapEnvelopeBuilder::new()
    ->header(new AuthenticationHeader(
        accessToken: $accessToken,
        companyCode: $companyCode,
    ))
    ->body(new Search(
        type: 'DIM',
        pattern: '*',
        field: 0,
        firstRow: 1,
        maxRows: 10,
    ));

$requestFactory = new DiscoverySoapRequestFactory();
$envelope = $builder->build();
$request = $envelope->toRequest(
    url: $endpoint,
    factory: $requestFactory,
);

header('Content-Type: text/plain; charset=utf-8');

echo "Method: {$request->getMethod()}\n";
echo "URI: {$request->getUri()}\n";
echo "SOAPAction: {$request->getHeaderLine('SOAPAction')}\n\n";
echo (string) $request->getBody() . "\n";

if ($executeRemote) {
    echo "\n--- Sending Remote Request ---\n";

    try {
        $soapClient = new SoapClient(Psr18ClientDiscovery::find(), $requestFactory);
        $soapResponse = $soapClient->sendEnvelope($endpoint, $envelope);

        $data = $soapResponse->mapTo(SearchResponse::class);
        var_dump($data);

        $response = $soapResponse->httpResponse();
        echo "Status: {$response->getStatusCode()} {$response->getReasonPhrase()}\n";
        echo "Response Content-Type: {$response->getHeaderLine('Content-Type')}\n\n";
        echo (string) $response->getBody() . "\n";
    } catch (SoapFaultException $exception) {
        echo 'SOAP fault: ' . $exception->getMessage() . "\n";
    } catch (Throwable $exception) {
        echo 'Remote request failed: ' . $exception->getMessage() . "\n";
        var_dump( get_class( $exception ) );
    }
} else {
    echo "\n--- Remote Request Disabled ---\n";
    echo "Set env vars and run with --send (CLI) or ?send=1 (browser):\n";
    echo "- TWINFIELD_ACCESS_TOKEN\n";
    echo "- TWINFIELD_COMPANY_CODE\n";
    echo "- TWINFIELD_SOAP_ENDPOINT (optional)\n";
}
