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
     * @return int
     */
    public function getNumberOfVatReturns(): int
    {
        return $this->numberOfVatReturns;
    }
}

