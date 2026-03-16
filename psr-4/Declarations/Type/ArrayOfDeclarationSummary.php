<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class ArrayOfDeclarationSummary
{
    /**
     * @var null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\DeclarationSummary>
     */
    private ?array $DeclarationSummary = null;

    /**
     * @return null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\DeclarationSummary>
     */
    public function getDeclarationSummary(): ?array
    {
        return $this->DeclarationSummary;
    }

    /**
     * @param null | array<int<0,max>, \SolveBeam\Twinfield\Declarations\Type\DeclarationSummary> $DeclarationSummary
     * @return static
     */
    public function withDeclarationSummary(?array $DeclarationSummary): static
    {
        $new = clone $this;
        $new->DeclarationSummary = $DeclarationSummary;

        return $new;
    }
}

