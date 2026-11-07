<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Helpers to write and validate transaction requests.
 *
 * @internal
 */
final class TransactionXmlWriter
{
    /**
     * Adds `<name>value</name>`, or nothing when the value is null: Twinfield then uses its default.
     */
    public static function add(XmlBuilder $xml, DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null) {
            $xml->add($parent, $name, $value);
        }
    }

    public static function performance(XmlBuilder $xml, DOMElement $line, ?Performance $performance): void
    {
        if ($performance === null) {
            return;
        }

        $xml->add($line, 'performancetype', $performance->type->value);
        self::add($xml, $line, 'performancecountry', $performance->country);
        self::add($xml, $line, 'performancevatnumber', $performance->vatNumber);
        self::add($xml, $line, 'performancedate', self::date($performance->date));
    }

    /**
     * @return numeric-string
     */
    public static function rate(string $rate): string
    {
        if (preg_match('~^\d+(?:\.\d+)?$~', $rate) !== 1 || !is_numeric($rate) || bccomp($rate, '0', 12) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not an exchange rate: use a positive numeric string.', $rate),
            );
        }

        return $rate;
    }

    /**
     * A dimension code on a line: 1–16 characters.
     */
    public static function dimension(string $field, string $code): void
    {
        if ($code === '') {
            throw new InvalidArgumentException(sprintf('Line %s must not be empty.', $field));
        }

        XmlValue::maxLength('line ' . $field, $code, 16);
    }

    /**
     * `yyyymmdd`, or null.
     */
    public static function date(?DateTimeInterface $date): ?string
    {
        return $date === null ? null : XmlValue::date($date);
    }

    public static function optionalText(string $field, ?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return XmlValue::maxLength($field, $value, $max);
    }
}
