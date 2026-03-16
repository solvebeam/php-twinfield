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
     * @param int $documentId
     * @return static
     */
    public function withDocumentId(int $documentId): static
    {
        $new = clone $this;
        $new->documentId = $documentId;

        return $new;
    }

    /**
     * @return bool
     */
    public function getIsMessageIdRequired(): bool
    {
        return $this->isMessageIdRequired;
    }

    /**
     * @param bool $isMessageIdRequired
     * @return static
     */
    public function withIsMessageIdRequired(bool $isMessageIdRequired): static
    {
        $new = clone $this;
        $new->isMessageIdRequired = $isMessageIdRequired;

        return $new;
    }
}

