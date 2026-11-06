<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetToApprovedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToApprovedResult = null;
}
