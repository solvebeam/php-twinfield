<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetTaxGroupVatReturnAsXbrl implements RequestInterface
{
    /**
     * @var int
     */
    private int $documentId;

    /**
     * @var bool
     */
    private bool $isMessageIdRequired;

    /**
     * Constructor
     *
     * @param int $documentId
     * @param bool $isMessageIdRequired
     */
    public function __construct(int $documentId, bool $isMessageIdRequired)
    {
        $this->documentId = $documentId;
        $this->isMessageIdRequired = $isMessageIdRequired;
    }
}

