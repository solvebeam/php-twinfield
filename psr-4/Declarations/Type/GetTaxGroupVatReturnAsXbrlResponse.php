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
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnAsXbrlResult
     * @return static
     */
    public function withGetTaxGroupVatReturnAsXbrlResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnAsXbrlResult): static
    {
        $new = clone $this;
        $new->GetTaxGroupVatReturnAsXbrlResult = $GetTaxGroupVatReturnAsXbrlResult;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn
    {
        return $this->vatReturn;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn $vatReturn
     * @return static
     */
    public function withVatReturn(?\SolveBeam\Twinfield\Declarations\Type\GetTaxGroupVatReturnAsXbrlResponseVatReturn $vatReturn): static
    {
        $new = clone $this;
        $new->vatReturn = $vatReturn;

        return $new;
    }
}

