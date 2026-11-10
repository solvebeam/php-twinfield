<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A code with the name Twinfield adds to it, e.g. a currency, bank book, user or office:
 * `<basecurrency name="Euro" shortname="Euro">EUR</basecurrency>`.
 */
final readonly class NamedReference
{
    public function __construct(
        public string $code,
        /** Null when Twinfield gives an empty name, e.g. for a bank book code that no longer exists. */
        public ?string $name = null,
        /** Null when empty or not given. */
        public ?string $shortName = null,
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Reference code must not be empty.');
        }
    }

    /**
     * From an element with a code and `name` and `shortname` attributes.
     *
     * @param bool $hasShortName False for elements without a `shortname` attribute, such as `taxgroup`.
     *
     * @internal
     */
    public static function fromXml(XmlElement $element, bool $hasShortName = true): self
    {
        if ($element->text() === '') {
            throw $element->invalid('a code');
        }

        return self::create($element, $hasShortName);
    }

    /**
     * Null for an empty element: the setting is not set, which is valid in Twinfield.
     *
     * @internal
     */
    public static function nullable(XmlElement $element, bool $hasShortName = true): ?self
    {
        return $element->text() === '' ? null : self::create($element, $hasShortName);
    }

    private static function create(XmlElement $element, bool $hasShortName): self
    {
        $name = $element->attribute('name');
        $shortName = $hasShortName ? $element->attribute('shortname') : '';

        return new self(
            $element->text(),
            $name === '' ? null : $name,
            $shortName === '' ? null : $shortName,
        );
    }
}
