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
     * @param int $Year
     * @return static
     */
    public function withYear(int $Year): static
    {
        $new = clone $this;
        $new->Year = $Year;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getPeriod(): ?string
    {
        return $this->Period;
    }

    /**
     * @param null | string $Period
     * @return static
     */
    public function withPeriod(?string $Period): static
    {
        $new = clone $this;
        $new->Period = $Period;

        return $new;
    }
}

