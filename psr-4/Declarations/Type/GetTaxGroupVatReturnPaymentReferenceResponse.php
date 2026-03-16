<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class GetTaxGroupVatReturnPaymentReferenceResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnPaymentReferenceResult = null;

    /**
     * @var null | string
     */
    private ?string $paymentReference = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
     */
    public function getGetTaxGroupVatReturnPaymentReferenceResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage
    {
        return $this->GetTaxGroupVatReturnPaymentReferenceResult;
    }

    /**
     * @return null | string
     */
    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }
}

