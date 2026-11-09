<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use InvalidArgumentException;

/**
 * Selection criterion of a browse column: the operator with its `from` and `to` values.
 *
 * Made by `BrowseQuery::equal()`, `between()` and the other criterion methods.
 */
final readonly class BrowseCriterion
{
    private function __construct(
        public BrowseOperator $operator,
        public string $from,
        /** Only for `between` and `notbetween`. */
        public ?string $to,
    ) {
    }

    public static function equal(string $value): self
    {
        return new self(BrowseOperator::Equal, self::value('Value', $value), null);
    }

    public static function like(string $pattern): self
    {
        return new self(BrowseOperator::Like, self::value('Pattern', $pattern), null);
    }

    public static function between(string $from, string $to): self
    {
        return new self(BrowseOperator::Between, self::value('From', $from), self::value('To', $to));
    }

    public static function notBetween(string $from, string $to): self
    {
        return new self(BrowseOperator::NotBetween, self::value('From', $from), self::value('To', $to));
    }

    /**
     * Twinfield allows at most 40 characters (`string(40)`). An empty value is refused: Twinfield
     * requires `to` for `between`, and an empty `from` selects nothing useful.
     */
    private static function value(string $name, string $value): string
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s value of a browse criterion must not be empty.', $name));
        }

        if (mb_strlen($value) > 40) {
            throw new InvalidArgumentException(sprintf(
                '%s value of a browse criterion must be at most 40 characters, got "%s".',
                $name,
                $value,
            ));
        }

        return $value;
    }
}
