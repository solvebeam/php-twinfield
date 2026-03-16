<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class MessageOfSetStatusMessages
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
     * @var \SolveBeam\Twinfield\Declarations\Type\SetStatusMessages
     */
    private \SolveBeam\Twinfield\Declarations\Type\SetStatusMessages $Code;

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
     * @return null | string
     */
    public function getText(): ?string
    {
        return $this->Text;
    }

    /**
     * @return \SolveBeam\Twinfield\Declarations\Type\SetStatusMessages
     */
    public function getCode(): \SolveBeam\Twinfield\Declarations\Type\SetStatusMessages
    {
        return $this->Code;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfString
     */
    public function getParameters(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfString
    {
        return $this->Parameters;
    }
}

