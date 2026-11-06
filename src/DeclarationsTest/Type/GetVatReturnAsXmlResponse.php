<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetVatReturnAsXmlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnAsXmlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn $vatReturn = null;
}
