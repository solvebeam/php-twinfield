<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class DocumentTimeFrame
{
    /**
     * @var int
     */
    private int $Year;

    /**
     * @var null | string
     */
    private ?string $Period = null;

    /**
     * @return int
     */
    public function getYear(): int
    {
        return $this->Year;
    }

    /**
     * @return null | string
     */
    public function getPeriod(): ?string
    {
        return $this->Period;
    }
}

