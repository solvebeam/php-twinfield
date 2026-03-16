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
     * @param null | string $Description
     * @return static
     */
    public function withDescription(?string $Description): static
    {
        $new = clone $this;
        $new->Description = $Description;

        return $new;
    }

    /**
     * @return int
     */
    public function getStepIndex(): int
    {
        return $this->StepIndex;
    }

    /**
     * @param int $StepIndex
     * @return static
     */
    public function withStepIndex(int $StepIndex): static
    {
        $new = clone $this;
        $new->StepIndex = $StepIndex;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getExtraInformation(): ?string
    {
        return $this->ExtraInformation;
    }

    /**
     * @param null | string $ExtraInformation
     * @return static
     */
    public function withExtraInformation(?string $ExtraInformation): static
    {
        $new = clone $this;
        $new->ExtraInformation = $ExtraInformation;

        return $new;
    }
}

