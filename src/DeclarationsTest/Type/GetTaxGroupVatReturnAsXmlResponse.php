<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetTaxGroupVatReturnAsXmlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnAsXmlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXmlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXmlResponseVatReturn $vatReturn = null;
}
