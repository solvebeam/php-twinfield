<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetVatReturnPaymentReference implements RequestInterface
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

    /**
     * @return int
     */
    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    /**
     * @param int $documentId
     * @return static
     */
    public function withDocumentId(int $documentId): static
    {
        $new = clone $this;
        $new->documentId = $documentId;

        return $new;
    }
}

