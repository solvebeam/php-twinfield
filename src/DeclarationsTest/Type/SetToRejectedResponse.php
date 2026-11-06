<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetToRejectedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToRejectedResult = null;
}
