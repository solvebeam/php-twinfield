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
}

