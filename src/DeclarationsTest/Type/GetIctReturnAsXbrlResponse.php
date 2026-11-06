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
}
