<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetVatReturnAsXml implements RequestInterface
{
    /**
     * @var int
     */
    private int $documentId;

    /**
     * Constructor
     *
     * @param int $documentId
     */
    public function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }
}
