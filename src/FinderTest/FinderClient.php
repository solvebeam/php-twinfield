<?php

namespace SolveBeam\Twinfield\Finder;

use Phpro\SoapClient\Type\ResultInterface;
use SolveBeam\Twinfield\Finder\Type;
use Phpro\SoapClient\Exception\SoapException;
use Phpro\SoapClient\Type\RequestInterface;

class FinderClient
{
    private \Phpro\SoapClient\Caller\Caller $caller;

    public function __construct(\Phpro\SoapClient\Caller\Caller $caller)
    {
        $this->caller = $caller;
    }

    /**
     * @param RequestInterface & Type\Search $parameters
     * @return ResultInterface & Type\SearchResponse
     * @throws SoapException
     */
    public function search(\SolveBeam\Twinfield\Finder\Type\Search $parameters): \SolveBeam\Twinfield\Finder\Type\SearchResponse
    {
        $response = ($this->caller)('Search', $parameters);

        \Psl\Type\instance_of(\SolveBeam\Twinfield\Finder\Type\SearchResponse::class)->assert($response);
        \Psl\Type\instance_of(\Phpro\SoapClient\Type\ResultInterface::class)->assert($response);

        return $response;
    }
}
