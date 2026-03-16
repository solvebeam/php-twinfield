<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetRangeOfSummariesSinceModifiedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesSinceModifiedResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetRangeOfSummariesSinceModifiedResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetRangeOfSummariesSinceModifiedResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesSinceModifiedResult
     * @return static
     */
    public function withGetRangeOfSummariesSinceModifiedResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesSinceModifiedResult): static
    {
        $new = clone $this;
        $new->GetRangeOfSummariesSinceModifiedResult = $GetRangeOfSummariesSinceModifiedResult;

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

