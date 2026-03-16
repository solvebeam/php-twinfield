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
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnPaymentReferenceResult
     * @return static
     */
    public function withGetVatReturnPaymentReferenceResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetVatReturnPaymentReferenceResult): static
    {
        $new = clone $this;
        $new->GetVatReturnPaymentReferenceResult = $GetVatReturnPaymentReferenceResult;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    /**
     * @param null | string $paymentReference
     * @return static
     */
    public function withPaymentReference(?string $paymentReference): static
    {
        $new = clone $this;
        $new->paymentReference = $paymentReference;

        return $new;
    }
}

