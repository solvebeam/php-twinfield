<?php

namespace SolveBeam\Twinfield\Finder\Type;

class ArrayOfArrayOfString
{
    /**
     * @var null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\ArrayOfString>
     */
    private ?array $ArrayOfString = null;

    /**
     * @return null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\ArrayOfString>
     */
    public function getArrayOfString(): ?array
    {
        return $this->ArrayOfString;
    }

    /**
     * @param null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\ArrayOfString> $ArrayOfString
     * @return static
     */
    public function withArrayOfString(?array $ArrayOfString): static
    {
        $new = clone $this;
        $new->ArrayOfString = $ArrayOfString;

        return $new;
    }
}

