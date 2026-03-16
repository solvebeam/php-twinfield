<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetToApprovedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToApprovedResult = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    public function getSetToApprovedResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
    {
        return $this->SetToApprovedResult;
    }
}

