<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetToRejectedResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToRejectedResult = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    public function getSetToRejectedResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
    {
        return $this->SetToRejectedResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToRejectedResult
     * @return static
     */
    public function withSetToRejectedResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToRejectedResult): static
    {
        $new = clone $this;
        $new->SetToRejectedResult = $SetToRejectedResult;

        return $new;
    }
}

