<?php

namespace SolveBeam\Twinfield\Finder;

use SolveBeam\Twinfield\Finder\FinderClient;
use SolveBeam\Twinfield\Finder\FinderClassmap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Phpro\SoapClient\Soap\DefaultEngineFactory;
use Phpro\SoapClient\Soap\EngineOptions;
use Phpro\SoapClient\Caller\EventDispatchingCaller;
use Phpro\SoapClient\Caller\EngineCaller;
use Soap\Encoding\EncoderRegistry;
use Soap\Psr18Transport\Psr18Transport;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr18ClientDiscovery;
use Soap\Psr18Transport\Middleware\SoapHeaderMiddleware;
use Soap\Xml\Builder\SoapHeader;
use Psr\Log\LoggerInterface;
use Http\Message\Formatter;
use function VeeWee\Xml\Dom\Builder\namespaced_element;
use function VeeWee\Xml\Dom\Builder\value;

class FinderClientFactory
{
    /**
     * This factory can be used as a starting point to create your own specialized
     * factory. Feel free to modify.
     *
     * @param non-empty-string $wsdl
     * @param list<callable> $soapHeaders Optional SOAP headers to add to requests
     * @param LoggerInterface|null $logger Optional PSR-3 logger for HTTP requests/responses
     * @param Formatter|null $formatter Optional formatter for log messages (use FullHttpMessageFormatter for complete HTTP logging)
     */
    public static function factory(
        string $wsdl, 
        array $soapHeaders = [], 
        ?LoggerInterface $logger = null,
        ?Formatter $formatter = null
    ): \SolveBeam\Twinfield\Finder\FinderClient
    {
        // Create HTTP client with optional plugins
        $httpClient = Psr18ClientDiscovery::find();
        
        $plugins = [];
        
        // Add SOAP header middleware if headers are provided
        if (!empty($soapHeaders)) {
            $plugins[] = new SoapHeaderMiddleware(...$soapHeaders);
        }
        
        // Add logger plugin if logger is provided and plugin class exists
        if ($logger !== null && class_exists('Http\Client\Common\Plugin\LoggerPlugin')) {
            $plugins[] = new \Http\Client\Common\Plugin\LoggerPlugin($logger, $formatter);
        }
        
        if (!empty($plugins)) {
            $httpClient = new PluginClient(
                $httpClient,
                $plugins
            );
        }

        $engine = DefaultEngineFactory::create(
            EngineOptions::defaults($wsdl)
                ->withEncoderRegistry(
                    EncoderRegistry::default()
                        ->addClassMapCollection(FinderClassmap::types())
                        ->addBackedEnumClassMapCollection(FinderClassmap::enums())
                )
                ->withTransport(
                    Psr18Transport::createForClient($httpClient)
                )
                // If you want to enable WSDL caching:
                // ->withCache() 
                // If you want to use Alternate HTTP settings:
                // ->withWsdlLoader()
                // If you want specific SOAP setting:
                // ->withWsdlParserContext()
                // ->withWsdlServiceSelectionCriteria()
        );

        $eventDispatcher = new EventDispatcher();
        $caller = new EventDispatchingCaller(new EngineCaller($engine), $eventDispatcher);

        return new FinderClient($caller);
    }

    /**
     * Example helper method to create an authentication header for Twinfield
     *
     * @param non-empty-string $namespace The namespace URI for the header
     * @param string $user Username
     * @param string $password Password
     * @return callable
     */
    public static function createAuthHeader(string $namespace, string $accessToken): callable
    {
        return new SoapHeader(
            $namespace,
            'twin:Header',
            namespaced_element($namespace, 'twin:AccessToken', value($accessToken))
        );
    }
}

