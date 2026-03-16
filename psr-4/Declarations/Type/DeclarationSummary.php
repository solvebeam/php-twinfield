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
     * @param int $Id
     * @return static
     */
    public function withId(int $Id): static
    {
        $new = clone $this;
        $new->Id = $Id;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getDocumentCode(): ?string
    {
        return $this->DocumentCode;
    }

    /**
     * @param null | string $DocumentCode
     * @return static
     */
    public function withDocumentCode(?string $DocumentCode): static
    {
        $new = clone $this;
        $new->DocumentCode = $DocumentCode;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getName(): ?string
    {
        return $this->Name;
    }

    /**
     * @param null | string $Name
     * @return static
     */
    public function withName(?string $Name): static
    {
        $new = clone $this;
        $new->Name = $Name;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame
     */
    public function getDocumentTimeFrame(): ?\SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame
    {
        return $this->DocumentTimeFrame;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame $DocumentTimeFrame
     * @return static
     */
    public function withDocumentTimeFrame(?\SolveBeam\Twinfield\Declarations\Type\DocumentTimeFrame $DocumentTimeFrame): static
    {
        $new = clone $this;
        $new->DocumentTimeFrame = $DocumentTimeFrame;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\DocumentStatus
     */
    public function getStatus(): ?\SolveBeam\Twinfield\Declarations\Type\DocumentStatus
    {
        return $this->Status;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\DocumentStatus $Status
     * @return static
     */
    public function withStatus(?\SolveBeam\Twinfield\Declarations\Type\DocumentStatus $Status): static
    {
        $new = clone $this;
        $new->Status = $Status;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    public function getAssignee(): ?\SolveBeam\Twinfield\Declarations\Type\CodeName
    {
        return $this->Assignee;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\CodeName $Assignee
     * @return static
     */
    public function withAssignee(?\SolveBeam\Twinfield\Declarations\Type\CodeName $Assignee): static
    {
        $new = clone $this;
        $new->Assignee = $Assignee;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\CodeName
     */
    public function getCompany(): ?\SolveBeam\Twinfield\Declarations\Type\CodeName
    {
        return $this->Company;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\CodeName $Company
     * @return static
     */
    public function withCompany(?\SolveBeam\Twinfield\Declarations\Type\CodeName $Company): static
    {
        $new = clone $this;
        $new->Company = $Company;

        return $new;
    }
}

