<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield;

use InvalidArgumentException;

/**
 * GUIDs of the REST API: the REST API identifies organisations, companies and everything in them by
 * GUID (`ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9`), not by code.
 *
 * The SDK keeps GUIDs as lower-case strings.
 */
final class Guid
{
    /** The REST API uses the empty GUID for "none", e.g. a VAT code without discount account. */
    public const EMPTY = '00000000-0000-0000-0000-000000000000';

    public static function isValid(string $guid): bool
    {
        return preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i', $guid) === 1;
    }

    /**
     * The GUID in lower case.
     *
     * @throws InvalidArgumentException When it is not a GUID, e.g. a code such as `1000`.
     */
    public static function of(string $guid, string $field = 'ID'): string
    {
        if (!self::isValid($guid)) {
            throw new InvalidArgumentException(sprintf(
                '%s "%s" is not a GUID, e.g. "ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9".',
                $field,
                $guid,
            ));
        }

        return strtolower($guid);
    }
}
