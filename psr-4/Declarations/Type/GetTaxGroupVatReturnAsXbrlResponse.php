<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetTaxGroupVatReturnAsXbrlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnAsXbrlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetTaxGroupVatReturnAsXbrlResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetTaxGroupVatReturnAsXbrlResult;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn
    {
        return $this->vatReturn;
    }
}

