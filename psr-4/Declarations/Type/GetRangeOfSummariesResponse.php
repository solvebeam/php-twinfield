<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetRangeOfSummariesResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetRangeOfSummariesResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetRangeOfSummariesResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesResult
     * @return static
     */
    public function withGetRangeOfSummariesResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesResult): static
    {
        $new = clone $this;
        $new->GetRangeOfSummariesResult = $GetRangeOfSummariesResult;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
    {
        return $this->vatReturn;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary $vatReturn
     * @return static
     */
    public function withVatReturn(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary $vatReturn): static
    {
        $new = clone $this;
        $new->vatReturn = $vatReturn;

        return $new;
    }
}

