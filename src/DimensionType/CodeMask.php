<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use Stringable;

/**
 * The mask that codes of a dimension type must match, e.g. `1[0-9][0-9][0-9]` for customers
 * 1000–1999, `P[0-9][0-9][0-9][0-9]` for projects or `*` for any code.
 *
 *   $mask = $twinfield->dimensionTypes()->get('DEB')->mask;
 *   $mask->matches('1010');   // true
 *   $mask->matches('20000');  // false
 *
 * matches() understands the syntax Twinfield uses: `[…]` one character from a set or range,
 * `*` any number of characters, `?` one character and `#` one digit; every other character
 * stands for itself. Codes are case-sensitive, as Twinfield codes are upper case.
 */
final readonly class CodeMask implements Stringable
{
    /** Maximum length of a mask (`string(80)`). */
    private const int MAX_LENGTH = 80;

    public function __construct(
        public string $pattern,
    ) {
        if ($pattern === '') {
            throw new InvalidArgumentException('Code mask must not be empty; use * for any code.');
        }

        XmlValue::maxLength('code mask', $pattern, self::MAX_LENGTH);
    }

    public static function of(self|string $mask): self
    {
        return $mask instanceof self ? $mask : new self($mask);
    }

    /**
     * The mask `*`, which allows any code.
     */
    public static function any(): self
    {
        return new self('*');
    }

    /**
     * Whether the mask allows any code (`*`).
     */
    public function allowsAnyCode(): bool
    {
        return $this->pattern === '*';
    }

    /**
     * Whether the code matches the mask, e.g. before creating a dimension of this type.
     */
    public function matches(string $code): bool
    {
        return preg_match($this->regex(), $code) === 1;
    }

    /**
     * The mask as a PCRE regular expression, anchored on both ends.
     */
    public function regex(): string
    {
        $regex = '';
        $length = strlen($this->pattern);

        for ($i = 0; $i < $length; $i++) {
            $character = $this->pattern[$i];
            $end = $character === '[' ? strpos($this->pattern, ']', $i + 1) : false;

            if ($end !== false && $end > $i + 1) {
                $regex .= self::characterClass(substr($this->pattern, $i + 1, $end - $i - 1));
                $i = $end;

                continue;
            }

            $regex .= match ($character) {
                '*' => '.*',
                '?' => '.',
                '#' => '[0-9]',
                default => preg_quote($character, '~'),
            };
        }

        return '~^' . $regex . '$~D';
    }

    public function __toString(): string
    {
        return $this->pattern;
    }

    /**
     * `[0-9]` or `[ABC]`: every character stands for itself, except `-` between two characters.
     */
    private static function characterClass(string $set): string
    {
        $class = '';
        $length = strlen($set);

        for ($i = 0; $i < $length; $i++) {
            $isRange = $set[$i] === '-' && $i > 0 && $i < $length - 1;
            $class .= $isRange ? '-' : preg_quote($set[$i], '~');
        }

        return '[' . $class . ']';
    }
}
