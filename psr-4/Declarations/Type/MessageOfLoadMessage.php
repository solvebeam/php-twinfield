<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class MessageOfLoadMessage
{
    /**
     * @var \SolveBeam\Twinfield\Declarations\Type\MessageType
     */
    private \SolveBeam\Twinfield\Declarations\Type\MessageType $Type;

    /**
     * @var null | string
     */
    private ?string $Text = null;

    /**
     * @var \SolveBeam\Twinfield\Declarations\Type\LoadMessage
     */
    private \SolveBeam\Twinfield\Declarations\Type\LoadMessage $Code;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfString
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfString $Parameters = null;

    /**
     * @return \SolveBeam\Twinfield\Declarations\Type\MessageType
     */
    public function getType(): \SolveBeam\Twinfield\Declarations\Type\MessageType
    {
        return $this->Type;
    }

    /**
     * @param \SolveBeam\Twinfield\Declarations\Type\MessageType $Type
     * @return static
     */
    public function withType(\SolveBeam\Twinfield\Declarations\Type\MessageType $Type): static
    {
        $new = clone $this;
        $new->Type = $Type;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getText(): ?string
    {
        return $this->Text;
    }

    /**
     * @param null | string $Text
     * @return static
     */
    public function withText(?string $Text): static
    {
        $new = clone $this;
        $new->Text = $Text;

        return $new;
    }

    /**
     * @return \SolveBeam\Twinfield\Declarations\Type\LoadMessage
     */
    public function getCode(): \SolveBeam\Twinfield\Declarations\Type\LoadMessage
    {
        return $this->Code;
    }

    /**
     * @param \SolveBeam\Twinfield\Declarations\Type\LoadMessage $Code
     * @return static
     */
    public function withCode(\SolveBeam\Twinfield\Declarations\Type\LoadMessage $Code): static
    {
        $new = clone $this;
        $new->Code = $Code;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfString
     */
    public function getParameters(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfString
    {
        return $this->Parameters;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfString $Parameters
     * @return static
     */
    public function withParameters(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfString $Parameters): static
    {
        $new = clone $this;
        $new->Parameters = $Parameters;

        return $new;
    }
}

