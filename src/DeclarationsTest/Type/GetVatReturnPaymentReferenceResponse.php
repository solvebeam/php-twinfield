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
}
