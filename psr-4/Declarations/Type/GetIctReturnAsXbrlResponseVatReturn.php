<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class GetIctReturnAsXbrlResponseVatReturn
{
    /**
     * @var string
     */
    private string $any;

    /**
     * @return string
     */
    public function getAny(): string
    {
        return $this->any;
    }

    /**
     * @param string $any
     * @return static
     */
    public function withAny(string $any): static
    {
        $new = clone $this;
        $new->any = $any;

        return $new;
    }
}

