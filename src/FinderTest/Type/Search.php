<?php

namespace SolveBeam\Twinfield\Finder\Type;

use Phpro\SoapClient\Type\RequestInterface;

class Search implements RequestInterface
{
    /**
     * @var null | string
     */
    private ?string $type = null;

    /**
     * @var null | string
     */
    private ?string $pattern = null;

    /**
     * @var int
     */
    private int $field;

    /**
     * @var int
     */
    private int $firstRow;

    /**
     * @var int
     */
    private int $maxRows;

    /**
     * @var null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
     */
    private ?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $options = null;

    /**
     * Constructor
     *
     * @param null | string $type
     * @param null | string $pattern
     * @param int $field
     * @param int $firstRow
     * @param int $maxRows
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $options
     */
    public function __construct(?string $type, ?string $pattern, int $field, int $firstRow, int $maxRows, ?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $options)
    {
        $this->type = $type;
        $this->pattern = $pattern;
        $this->field = $field;
        $this->firstRow = $firstRow;
        $this->maxRows = $maxRows;
        $this->options = $options;
    }

    /**
     * @return null | string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param null | string $type
     * @return static
     */
    public function withType(?string $type): static
    {
        $new = clone $this;
        $new->type = $type;

        return $new;
    }

    /**
     * @return null | string
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * @param null | string $pattern
     * @return static
     */
    public function withPattern(?string $pattern): static
    {
        $new = clone $this;
        $new->pattern = $pattern;

        return $new;
    }

    /**
     * @return int
     */
    public function getField(): int
    {
        return $this->field;
    }

    /**
     * @param int $field
     * @return static
     */
    public function withField(int $field): static
    {
        $new = clone $this;
        $new->field = $field;

        return $new;
    }

    /**
     * @return int
     */
    public function getFirstRow(): int
    {
        return $this->firstRow;
    }

    /**
     * @param int $firstRow
     * @return static
     */
    public function withFirstRow(int $firstRow): static
    {
        $new = clone $this;
        $new->firstRow = $firstRow;

        return $new;
    }

    /**
     * @return int
     */
    public function getMaxRows(): int
    {
        return $this->maxRows;
    }

    /**
     * @param int $maxRows
     * @return static
     */
    public function withMaxRows(int $maxRows): static
    {
        $new = clone $this;
        $new->maxRows = $maxRows;

        return $new;
    }

    /**
     * @return null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
     */
    public function getOptions(): ?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString
    {
        return $this->options;
    }

    /**
     * @param null | \SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $options
     * @return static
     */
    public function withOptions(?\SolveBeam\Twinfield\Finder\Type\ArrayOfArrayOfString $options): static
    {
        $new = clone $this;
        $new->options = $options;

        return $new;
    }
}
