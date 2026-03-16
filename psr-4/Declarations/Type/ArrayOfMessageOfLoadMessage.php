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

    /**
     * @param null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\MessageOfLoadMessage> $MessageOfLoadMessage
     * @return static
     */
    public function withMessageOfLoadMessage(?array $MessageOfLoadMessage): static
    {
        $new = clone $this;
        $new->MessageOfLoadMessage = $MessageOfLoadMessage;

        return $new;
    }
}

