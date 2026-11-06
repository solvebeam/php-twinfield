<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ProcessXml;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Value types of the XML web services.
 *
 *   XmlValue::date(new DateTimeImmutable('2025-01-31')); // 20250131
 *   XmlValue::money('12.5');                             // 12.50
 *   XmlValue::period(2025, 1);                           // 2025/01
 *
 * @see https://developers.twinfield.com/documentation/api/types/xml-web-services/#value-types
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
     * `yyyy/pp`, e.g. `2025/01`.
     */
    public static function period(int $year, int $number): string
    {
        if ($year < 1000 || $year > 9999 || $number < 0 || $number > 99) {
            throw new InvalidArgumentException(sprintf('%d/%d is not a period yyyy/pp.', $year, $number));
        }

        return sprintf('%04d/%02d', $year, $number);
    }

    /**
     * `yyyy/p` or `yyyy/pp`.
     *
     * @return array{year: int, number: int}
     */
    public static function toPeriod(string $value): array
    {
        if (preg_match('~^(\d{4})/(\d{1,2})$~', $value, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a period yyyy/p or yyyy/pp.', $value));
        }

        return ['year' => (int) $matches[1], 'number' => (int) $matches[2]];
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
     * Code of at most 16 characters: A-Z, 0-9, `_`, `-` and `+`.
     */
    public static function code(string $code): string
    {
        if (preg_match('~^[A-Z0-9_+-]{1,16}$~', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a code: 1-16 times A-Z, 0-9, _, - or +.', $code));
        }

        return $code;
    }
}
