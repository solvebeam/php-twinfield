<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetIctReturnAsXbrlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetIctReturnAsXbrlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponseVatReturn $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetIctReturnAsXbrlResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetIctReturnAsXbrlResult;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXbrlResponseVatReturn
    {
        return $this->vatReturn;
    }
}

