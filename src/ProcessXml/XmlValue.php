<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Period\Period;

/**
 * Value types of the XML web services.
 *
 *   XmlValue::date(new DateTimeImmutable('2025-01-31')); // 20250131
 *   XmlValue::money('12.5');                             // 12.50
 *   XmlValue::period(2025, 1);                           // 2025/01
 *
 * @see https://developers.twinfield.com/documentation/api/types/xml-web-services/#value-types
 *
 * @internal
 */
final class XmlValue
{
    /**
     * `yyyymmdd`.
     */
    public static function date(DateTimeInterface $date): string
    {
        return $date->format('Ymd');
    }

    public static function toDate(string $value): DateTimeImmutable
    {
        $date = preg_match('~^\d{8}$~', $value) === 1 ? DateTimeImmutable::createFromFormat('!Ymd', $value) : false;

        if ($date === false || $date->format('Ymd') !== $value) {
            throw new InvalidArgumentException(sprintf('"%s" is not a date in the format yyyymmdd.', $value));
        }

        return $date;
    }

    /**
     * `0.00`, with a dot as decimal separator. Pass amounts as string or int; floats are not exact.
     */
    public static function money(string|int $amount): string
    {
        if (is_int($amount)) {
            return $amount . '.00';
        }

        if (preg_match('~^(-?\d+)(?:\.(\d{1,2}))?$~', $amount, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not an amount with at most 2 decimals.', $amount));
        }

        return $matches[1] . '.' . str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * `yyyy/pp`, e.g. `2025/01`. The rules are those of `Period`.
     */
    public static function period(int $year, int $number): string
    {
        return (string) Period::of($year, $number);
    }

    /**
     * `yyyy/p` or `yyyy/pp`. The rules are those of `Period`; use `Period::fromString()` for a `Period`.
     *
     * @return array{year: int, number: int}
     */
    public static function toPeriod(string $value): array
    {
        $period = Period::fromString($value);

        return ['year' => $period->year, 'number' => $period->number];
    }

    public static function boolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    public static function toBoolean(string $value): bool
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => throw new InvalidArgumentException(sprintf('"%s" is not a boolean (true or false).', $value)),
        };
    }

    /**
     * Checks a string field against its maximum length in characters, e.g. `string(40)`.
     */
    public static function maxLength(string $field, string $value, int $max): string
    {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf(
                '%s must be at most %d characters, %d given: "%s".',
                ucfirst($field),
                $max,
                mb_strlen($value),
                $value,
            ));
        }

        return $value;
    }

    /**
     * Code of at most 16 characters: A-Z, 0-9, `_`, `-` and `+`.
     */
    public static function code(string $code): string
    {
        if (preg_match('~^[A-Z0-9_+-]{1,16}$~', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a code: 1-16 times A-Z, 0-9, _, - or +.', $code));
        }

        return $code;
    }

    /**
     * VAT code: a code (see code()), or `#`, the VAT code "no VAT" (Geen btw) of Twinfield.
     */
    public static function vatCode(string $vatCode): string
    {
        return $vatCode === '#' ? $vatCode : self::code($vatCode);
    }
}
