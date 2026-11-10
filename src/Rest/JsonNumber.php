<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use InvalidArgumentException;

/**
 * An exact JSON number for a request body, from a numeric string: e.g. a percentage `21.5` or a
 * quantity. Use `Amount` for money.
 *
 *   JsonWriter::encode(['percent' => JsonNumber::of('21.5')]);   // {"percent":21.5}
 */
final readonly class JsonNumber
{
    /**
     * @param numeric-string $value
     */
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @param numeric-string $value E.g. `'21'`, `'21.5'` or `'-0.25'`.
     *
     * @throws InvalidArgumentException For anything else, such as `'1e3'`, `'1,5'` or `'.5'`.
     */
    public static function of(string $value): self
    {
        if (preg_match('~^-?(0|[1-9]\d*)(\.\d+)?$~', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a number: use e.g. "21.5".', $value));
        }

        return new self($value);
    }
}
