<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class DocumentStatus
{
    /**
     * @var null | string
     */
    private ?string $Description = null;

    /**
     * @var int
     */
    private int $StepIndex;

    /**
     * @var null | string
     */
    private ?string $ExtraInformation = null;

    /**
     * @return null | string
     */
    public function getDescription(): ?string
    {
        return $this->Description;
    }

    /**
     * @return int
     */
    public function getStepIndex(): int
    {
        return $this->StepIndex;
    }

    /**
     * @return null | string
     */
    public function getExtraInformation(): ?string
    {
        return $this->ExtraInformation;
    }
}

