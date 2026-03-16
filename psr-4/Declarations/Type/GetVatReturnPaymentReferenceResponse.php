<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetVatReturnPaymentReferenceResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnPaymentReferenceResult = null;

    /**
     * @var null | string
     */
    private ?string $paymentReference = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetVatReturnPaymentReferenceResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetVatReturnPaymentReferenceResult;
    }

    /**
     * @return null | string
     */
    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }
}

