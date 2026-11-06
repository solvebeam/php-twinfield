<?php

namespace SolveBeam\Twinfield\Finder\Type;

use Phpro\SoapClient\Type\ResultInterface;

class SearchResponse implements ResultInterface
{
    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes
     */
    private ?\SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes $SearchResult = null;

    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\FinderData
     */
    private ?\SolveBeam\Twinfield\Finder\Type\FinderData $data = null;

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes
     */
    public function getSearchResult(): ?\SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes
    {
        return $this->SearchResult;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes $SearchResult
     * @return static
     */
    public function withSearchResult(?\SolveBeam\Twinfield\Finder\Type\ArrayOfMessageOfErrorCodes $SearchResult): static
    {
        $new = clone $this;
        $new->SearchResult = $SearchResult;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\FinderData
     */
    public function getData(): ?\SolveBeam\Twinfield\Finder\Type\FinderData
    {
        return $this->data;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\FinderData $data
     * @return static
     */
    public function withData(?\SolveBeam\Twinfield\Finder\Type\FinderData $data): static
    {
        $new = clone $this;
        $new->data = $data;

        return $new;
    }
}
