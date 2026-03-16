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

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToApprovedResult
     * @return static
     */
    public function withSetToApprovedResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToApprovedResult): static
    {
        $new = clone $this;
        $new->SetToApprovedResult = $SetToApprovedResult;

        return $new;
    }
}

