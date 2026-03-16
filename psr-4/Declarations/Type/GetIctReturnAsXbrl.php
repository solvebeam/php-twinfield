<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\RequestInterface;

class GetIctReturnAsXbrl implements RequestInterface
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

    /**
     * @return int
     */
    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    /**
     * @return bool
     */
    public function getIsMessageIdRequired(): bool
    {
        return $this->isMessageIdRequired;
    }
}

