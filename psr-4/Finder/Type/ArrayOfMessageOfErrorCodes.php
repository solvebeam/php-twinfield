<?php

namespace SolveBeam\Twinfield\Finder\Type;

class ArrayOfMessageOfErrorCodes
{
    /**
     * @var null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\MessageOfErrorCodes>
     */
    private ?array $MessageOfErrorCodes = null;

    /**
     * @return null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\MessageOfErrorCodes>
     */
    public function getMessageOfErrorCodes(): ?array
    {
        return $this->MessageOfErrorCodes;
    }

    /**
     * @param null | array<int<0,max>, \SolveBeam\Twinfield\Finder\Type\MessageOfErrorCodes> $MessageOfErrorCodes
     * @return static
     */
    public function withMessageOfErrorCodes(?array $MessageOfErrorCodes): static
    {
        $new = clone $this;
        $new->MessageOfErrorCodes = $MessageOfErrorCodes;

        return $new;
    }
}

