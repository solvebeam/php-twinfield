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
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnPaymentReferenceResult
     * @return static
     */
    public function withGetTaxGroupVatReturnPaymentReferenceResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfLoadMessage $GetTaxGroupVatReturnPaymentReferenceResult): static
    {
        $new = clone $this;
        $new->GetTaxGroupVatReturnPaymentReferenceResult = $GetTaxGroupVatReturnPaymentReferenceResult;

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

