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
     * @return null | string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @param null | string $reason
     * @return static
     */
    public function withReason(?string $reason): static
    {
        $new = clone $this;
        $new->reason = $reason;

        return $new;
    }
}

