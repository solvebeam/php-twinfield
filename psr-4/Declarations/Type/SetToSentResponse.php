<?php

namespace SolveBeam\Twinfield\Declarations\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SetToSentResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    private ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToSentResult = null;

    /**
     * @return null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
     */
    public function getSetToSentResult(): ?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages
    {
        return $this->SetToSentResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToSentResult
     * @return static
     */
    public function withSetToSentResult(?\SolveBeam\Twinfield\Declarations\Type\ArrayOfMessageOfSetStatusMessages $SetToSentResult): static
    {
        $new = clone $this;
        $new->SetToSentResult = $SetToSentResult;

        return $new;
    }
}

