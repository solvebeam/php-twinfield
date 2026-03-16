<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetVatReturnAsXbrlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnAsXbrlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponseVatReturn $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetVatReturnAsXbrlResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetVatReturnAsXbrlResult;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXbrlResponseVatReturn
    {
        return $this->vatReturn;
    }
}

