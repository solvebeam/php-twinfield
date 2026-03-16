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

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetVatReturnAsXmlResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetVatReturnAsXmlResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnAsXmlResult
     * @return static
     */
    public function withGetVatReturnAsXmlResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnAsXmlResult): static
    {
        $new = clone $this;
        $new->GetVatReturnAsXmlResult = $GetVatReturnAsXmlResult;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn
    {
        return $this->vatReturn;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn $vatReturn
     * @return static
     */
    public function withVatReturn(?\SolveBeam\Twinfield\Declarations\Type\GetVatReturnAsXmlResponseVatReturn $vatReturn): static
    {
        $new = clone $this;
        $new->vatReturn = $vatReturn;

        return $new;
    }
}

