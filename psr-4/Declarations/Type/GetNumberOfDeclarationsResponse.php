<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetNumberOfDeclarationsResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetNumberOfDeclarationsResult = null;

    /**
     * @var int
     */
    private int $numberOfVatReturns;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetNumberOfDeclarationsResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetNumberOfDeclarationsResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetNumberOfDeclarationsResult
     * @return static
     */
    public function withGetNumberOfDeclarationsResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetNumberOfDeclarationsResult): static
    {
        $new = clone $this;
        $new->GetNumberOfDeclarationsResult = $GetNumberOfDeclarationsResult;

        return $new;
    }

    /**
     * @return int
     */
    public function getNumberOfVatReturns(): int
    {
        return $this->numberOfVatReturns;
    }

    /**
     * @param int $numberOfVatReturns
     * @return static
     */
    public function withNumberOfVatReturns(int $numberOfVatReturns): static
    {
        $new = clone $this;
        $new->numberOfVatReturns = $numberOfVatReturns;

        return $new;
    }
}

