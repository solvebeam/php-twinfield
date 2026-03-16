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
     * @return null | string
     */
    public function getName(): ?string
    {
        return $this->Name;
    }
}

