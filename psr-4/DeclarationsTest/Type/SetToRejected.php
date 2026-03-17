<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class SetToRejected implements RequestInterface
{
    /**
     * @var int
     */
    private int $documentId;

    /**
     * @var null | string
     */
    private ?string $reason = null;

    /**
     * Constructor
     *
     * @param int $documentId
     * @param null | string $reason
     */
    public function __construct(int $documentId, ?string $reason)
    {
        $this->documentId = $documentId;
        $this->reason = $reason;
    }
}

