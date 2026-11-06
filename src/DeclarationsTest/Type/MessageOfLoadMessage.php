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
}
