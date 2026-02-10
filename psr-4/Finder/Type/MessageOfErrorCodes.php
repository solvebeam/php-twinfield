<?php

namespace SolveBeam\Twinfield\Finder\Type;

class MessageOfErrorCodes
{
    /**
     * @var \SolveBeam\Twinfield\Finder\Type\MessageType
     */
    private \SolveBeam\Twinfield\Finder\Type\MessageType $Type;

    /**
     * @var null | string
     */
    private ?string $Text = null;

    /**
     * @var \SolveBeam\Twinfield\Finder\Type\ErrorCodes
     */
    private \SolveBeam\Twinfield\Finder\Type\ErrorCodes $Code;

    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString
     */
    private ?\SolveBeam\Twinfield\Finder\Type\ArrayOfString $Parameters = null;

    /**
     * @return \SolveBeam\Twinfield\Finder\Type\MessageType
     */
    public function getType(): \SolveBeam\Twinfield\Finder\Type\MessageType
    {
        return $this->Type;
    }

    /**
     * @param \SolveBeam\Twinfield\Finder\Type\MessageType $Type
     * @return static
     */
    public function withType(\SolveBeam\Twinfield\Finder\Type\MessageType $Type): static
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
     * @return \SolveBeam\Twinfield\Finder\Type\ErrorCodes
     */
    public function getCode(): \SolveBeam\Twinfield\Finder\Type\ErrorCodes
    {
        return $this->Code;
    }

    /**
     * @param \SolveBeam\Twinfield\Finder\Type\ErrorCodes $Code
     * @return static
     */
    public function withCode(\SolveBeam\Twinfield\Finder\Type\ErrorCodes $Code): static
    {
        $new = clone $this;
        $new->Code = $Code;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString
     */
    public function getParameters(): ?\SolveBeam\Twinfield\Finder\Type\ArrayOfString
    {
        return $this->Parameters;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString $Parameters
     * @return static
     */
    public function withParameters(?\SolveBeam\Twinfield\Finder\Type\ArrayOfString $Parameters): static
    {
        $new = clone $this;
        $new->Parameters = $Parameters;

        return $new;
    }
}

