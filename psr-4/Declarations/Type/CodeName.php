<?php

namespace SolveBeam\Twinfield\Declarations\Type;

class CodeName
{
    /**
     * @var null | string
     */
    private ?string $Code = null;

    /**
     * @var null | string
     */
    private ?string $Name = null;

    /**
     * @return null | string
     */
    public function getCode(): ?string
    {
        return $this->Code;
    }

    /**
     * @param null | string $Code
     * @return static
     */
    public function withCode(?string $Code): static
    {
        $new = clone $this;
        $new->Code = $Code;

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
}

