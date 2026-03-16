<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class ArrayOfMessageOfSetStatusMessages
{
    /**
     * @var null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\MessageOfSetStatusMessages>
     */
    private ?array $MessageOfSetStatusMessages = null;

    /**
     * @return null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\MessageOfSetStatusMessages>
     */
    public function getMessageOfSetStatusMessages(): ?array
    {
        return $this->MessageOfSetStatusMessages;
    }
}

