<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Readers for transaction responses.
 *
 * Twinfield leaves optional elements out of a transaction response when they have no value
 * (see the response examples in the documentation), so `optional*()` accepts a missing and
 * an empty element. Values that are there must still be valid.
 *
 * @internal
 */
final class TransactionXml
{
    public static function amount(XmlElement $parent, string $name): Amount
    {
        return self::toAmount($parent->child($name));
    }

    public static function optionalAmount(XmlElement $parent, string $name): ?Amount
    {
        $child = self::optionalChild($parent, $name);

        return $child === null ? null : self::toAmount($child);
    }

    /**
     * Exchange rate, e.g. `1.500000000`.
     *
     * @return ?numeric-string
     */
    public static function optionalRate(XmlElement $parent, string $name): ?string
    {
        $child = self::optionalChild($parent, $name);

        if ($child === null) {
            return null;
        }

        $text = $child->text();

        if (preg_match('~^\d+(?:\.\d+)?$~', $text) !== 1 || !is_numeric($text)) {
            throw $child->invalid('an exchange rate');
        }

        return $text;
    }

    public static function optionalString(XmlElement $parent, string $name): ?string
    {
        return self::optionalChild($parent, $name)?->text();
    }

    public static function optionalInt(XmlElement $parent, string $name): ?int
    {
        return self::optionalChild($parent, $name) === null ? null : $parent->int($name);
    }

    public static function optionalDate(XmlElement $parent, string $name): ?DateTimeImmutable
    {
        return self::optionalChild($parent, $name) === null ? null : $parent->nullableDate($name);
    }

    public static function date(XmlElement $parent, string $name): DateTimeImmutable
    {
        return $parent->nullableDate($name) ?? throw $parent->child($name)->invalid('a date yyyymmdd');
    }

    public static function optionalDateTime(XmlElement $parent, string $name): ?DateTimeImmutable
    {
        return self::optionalChild($parent, $name) === null ? null : $parent->dateTime($name);
    }

    public static function dimension(XmlElement $parent, string $name): DimensionReference
    {
        return self::optionalDimension($parent, $name) ?? throw new UnexpectedResponseException(
            sprintf('%s/%s is missing or empty.', $parent->path, $name),
        );
    }

    public static function optionalDimension(XmlElement $parent, string $name): ?DimensionReference
    {
        $child = self::optionalChild($parent, $name);

        if ($child === null) {
            return null;
        }

        $attribute = static function (string $attribute) use ($child): ?string {
            $value = $child->element->getAttribute($attribute);

            return $value === '' ? null : $value;
        };

        return new DimensionReference(
            code: $child->text(),
            // The documentation names it `dimensiontype`; responses use `type`.
            type: $attribute('type') ?? $attribute('dimensiontype'),
            name: $attribute('name'),
        );
    }

    public static function period(XmlElement $parent, string $name): FinancialPeriod
    {
        $child = $parent->child($name);

        try {
            return FinancialPeriod::fromString($child->text());
        } catch (InvalidArgumentException) {
            throw $child->invalid('a period yyyy/pp');
        }
    }

    public static function performance(XmlElement $line): ?Performance
    {
        $type = self::optionalChild($line, 'performancetype');

        if ($type === null) {
            return null;
        }

        try {
            return Performance::create(
                type: $line->enum('performancetype', PerformanceType::class),
                country: self::optionalString($line, 'performancecountry'),
                vatNumber: self::optionalString($line, 'performancevatnumber'),
                date: self::optionalDate($line, 'performancedate'),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s has invalid performance details: %s', $line->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * The child element when it is there and not empty.
     */
    public static function optionalChild(XmlElement $parent, string $name): ?XmlElement
    {
        $child = $parent->nullableChild($name);

        return $child === null || $child->text() === '' ? null : $child;
    }

    private static function toAmount(XmlElement $element): Amount
    {
        $text = $element->text();

        if (!is_numeric($text)) {
            throw $element->invalid('an amount');
        }

        try {
            return Amount::of($text);
        } catch (InvalidArgumentException) {
            throw $element->invalid('an amount with at most 2 decimals');
        }
    }
}
