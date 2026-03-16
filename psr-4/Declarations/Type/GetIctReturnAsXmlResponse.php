<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetIctReturnAsXmlResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetIctReturnAsXmlResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn $vatReturn = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetIctReturnAsXmlResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetIctReturnAsXmlResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetIctReturnAsXmlResult
     * @return static
     */
    public function withGetIctReturnAsXmlResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetIctReturnAsXmlResult): static
    {
        $new = clone $this;
        $new->GetIctReturnAsXmlResult = $GetIctReturnAsXmlResult;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn
     */
    public function getVatReturn(): ?\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn
    {
        return $this->vatReturn;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn $vatReturn
     * @return static
     */
    public function withVatReturn(?\SolveBeam\Twinfield\Declarations\Type\GetIctReturnAsXmlResponseVatReturn $vatReturn): static
    {
        $new = clone $this;
        $new->vatReturn = $vatReturn;

        return $new;
    }
}

