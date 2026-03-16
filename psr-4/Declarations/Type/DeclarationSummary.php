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

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->Id;
    }

    /**
     * @return null | string
     */
    public function getDocumentCode(): ?string
    {
        return $this->DocumentCode;
    }

    /**
     * @return null | string
     */
    public function getName(): ?string
    {
        return $this->Name;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame
     */
    public function getDocumentTimeFrame(): ?\SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame
    {
        return $this->DocumentTimeFrame;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\DocumentStatus
     */
    public function getStatus(): ?\SolveBeam\Twinfield\Declarations\Type\DocumentStatus
    {
        return $this->Status;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    public function getAssignee(): ?\SolveBeam\Twinfield\Declarations\Type\CodeName
    {
        return $this->Assignee;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    public function getCompany(): ?\SolveBeam\Twinfield\Declarations\Type\CodeName
    {
        return $this->Company;
    }
}

