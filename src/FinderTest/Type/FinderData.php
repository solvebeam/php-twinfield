<?php

namespace SolveBeam\Twinfield\Finder\Type;

class FinderData
{
    /**
     * @var int
     */
    private int $TotalRows;

    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString
     */
    private ?\SolveBeam\Twinfield\Finder\Type\ArrayOfString $Columns = null;

    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
     */
    private ?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $Items = null;

    /**
     * @return int
     */
    public function getTotalRows(): int
    {
        return $this->TotalRows;
    }

    /**
     * @param int $TotalRows
     * @return static
     */
    public function withTotalRows(int $TotalRows): static
    {
        $new = clone $this;
        $new->TotalRows = $TotalRows;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString
     */
    public function getColumns(): ?\SolveBeam\Twinfield\Finder\Type\ArrayOfString
    {
        return $this->Columns;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfString $Columns
     * @return static
     */
    public function withColumns(?\SolveBeam\Twinfield\Finder\Type\ArrayOfString $Columns): static
    {
        $new = clone $this;
        $new->Columns = $Columns;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
     */
    public function getItems(): ?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
    {
        return $this->Items;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $Items
     * @return static
     */
    public function withItems(?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $Items): static
    {
        $new = clone $this;
        $new->Items = $Items;

        return $new;
    }
}
