<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class ArrayOfMessageOfLoadMessage
{
    /**
     * @var null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\MessageOfLoadMessage>
     */
    private ?array $MessageOfLoadMessage = null;

    /**
     * @return null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\MessageOfLoadMessage>
     */
    public function getMessageOfLoadMessage(): ?array
    {
        return $this->MessageOfLoadMessage;
    }
}

