<?php

declare(strict_types=1);

use SolveBeam\Twinfield\Finder\FinderClientFactory;
use Soap\Xml\Builder\SoapHeader;
use function VeeWee\Xml\Dom\Builder\namespaced_element;
use function VeeWee\Xml\Dom\Builder\value;
use function VeeWee\Xml\Dom\Builder\children;

require_once __DIR__ . '/../../vendor/autoload.php';

$namespace = 'http://www.twinfield.com/';
$accessToken = '4b654e8382b7acf763c53f7387ee6f6d';

$soapHeaders = [
    new SoapHeader(
        $namespace,
        'twin:Header',
        children(
            namespaced_element($namespace, 'twin:AccessToken', value($accessToken))
        )
    ),
];

$finder = FinderClientFactory::factory(
    'https://api.accounting.twinfield.com/webservices/finder.asmx?wsdl',
    $soapHeaders
);

$test = $finder->search( new \SolveBeam\Twinfield\Finder\Type\Search( 'OFF', '*', 1, 1, 50, new \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString() ) );

var_dump( $test );
