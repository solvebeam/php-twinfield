<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use BackedEnum;
use InvalidArgumentException;
use JsonException;
use stdClass;
use SolveBeam\Twinfield\Rate\UnitPrice;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Encodes a request body for the REST API. Amounts and other exact numbers become JSON numbers
 * with exactly their digits, without a float in between:
 *
 *   JsonWriter::encode(['netPrice' => Amount::of('121.10'), 'name' => 'Bike']);
 *   // {"netPrice":121.10,"name":"Bike"}
 *
 * Values: scalars, null, arrays, `Amount`, `UnitPrice`, `JsonNumber` and backed enums (their value).
 * Use `(object) []` for an empty JSON object.
 */
final class JsonWriter
{
    /**
     * @param array<array-key, mixed>|object $body
     *
     * @throws InvalidArgumentException For a value that can't be sent, such as a float or a DateTime:
     *                                  format dates in the resource, as the endpoint expects them.
     */
    public static function encode(array|object $body): string
    {
        $marker = '@number-' . bin2hex(random_bytes(8)) . '@';
        $numbers = [];
        $prepared = self::prepare($body, $marker, $numbers, '');

        try {
            $json = json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'The request body can\'t be encoded as JSON: ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        foreach ($numbers as $index => $number) {
            $json = str_replace(sprintf('"%s%d"', $marker, $index), $number, $json);
        }

        return $json;
    }

    /**
     * @param list<string> $numbers
     */
    private static function prepare(mixed $value, string $marker, array &$numbers, string $path): mixed
    {
        if ($value instanceof Amount || $value instanceof UnitPrice || $value instanceof JsonNumber) {
            $numbers[] = $value->value;

            return $marker . (count($numbers) - 1);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                'Request body value %s is a float: use Amount, UnitPrice or JsonNumber with a numeric string.',
                $path === '' ? '(root)' : $path,
            ));
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $prepared = [];

            foreach ($value as $key => $item) {
                $itemPath = $path === '' ? (string) $key : $path . '/' . $key;
                $prepared[$key] = self::prepare($item, $marker, $numbers, $itemPath);
            }

            return $prepared;
        }

        if ($value instanceof stdClass) {
            return (object) self::prepare((array) $value, $marker, $numbers, $path);
        }

        throw new InvalidArgumentException(sprintf(
            'Request body value %s is a %s, which can\'t be sent as JSON.',
            $path === '' ? '(root)' : $path,
            get_debug_type($value),
        ));
    }
}
