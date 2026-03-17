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
}

