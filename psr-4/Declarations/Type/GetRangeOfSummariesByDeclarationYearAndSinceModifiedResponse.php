<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetRangeOfSummariesByDeclarationYearAndSinceModifiedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetRangeOfSummariesByDeclarationYearAndSinceModifiedResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetRangeOfSummariesByDeclarationYearAndSinceModifiedResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetRangeOfSummariesByDeclarationYearAndSinceModifiedResult;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfDeclarationSummary
    {
        return $this->vatReturn;
    }
}

