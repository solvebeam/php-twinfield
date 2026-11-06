<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class DeclarationSummary
{
    /**
     * @var int
     */
    private int $Id;

    /**
     * @var null | string
     */
    private ?string $DocumentCode = null;

    /**
     * @var null | string
     */
    private ?string $Name = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame $DocumentTimeFrame = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\DocumentStatus
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\DocumentStatus $Status = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\CodeName $Assignee = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\CodeName $Company = null;
}

