<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

use SolveBeam\Twinfield\Guid;
use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A JSON object of a REST API response, with typed readers that throw an UnexpectedResponseException
 * (with the path, e.g. `items[2]/vatRates[0]/percent`) when a value is missing or invalid.
 *
 *   $vatCode = $response->json();
 *
 *   $id = $vatCode->uuid('id');
 *   $name = $vatCode->string('name');
 *
 *   foreach ($vatCode->list('vatRates') as $rate) {
 *       echo $rate->decimal('percent'), PHP_EOL;
 *   }
 *
 * JSON numbers are read as exact strings, never as floats: `decimal()`, `amount()`. The REST API sends
 * money as a JSON number (`121.1`), which PHP decodes as float; the reader turns it back into the
 * shortest string with the same value (at most 15 significant digits), so `121.1` stays `121.1`.
 *
 * The `nullable…()` readers give null for a JSON `null` or a missing property. Only use them where
 * that is valid Twinfield behaviour: the REST API leaves out properties that have no value.
 */
final readonly class JsonValue
{
    /**
     * @param array<array-key, mixed> $data A decoded JSON object.
     */
    public function __construct(
        private array $data,
        /** Path for error messages, e.g. `items[2]`; empty for the root. */
        public string $path = '',
    ) {
    }

    /**
     * @throws UnexpectedResponseException When the JSON is invalid or not an object.
     */
    public static function decode(string $json, string $path = ''): self
    {
        $data = self::decodeJson($json);

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new UnexpectedResponseException(sprintf('%s is not a JSON object.', self::describe($path)));
        }

        return new self($data, $path);
    }

    /**
     * A response body that is a JSON array of objects, e.g. the list of articles.
     *
     * @return list<self>
     *
     * @throws UnexpectedResponseException When the JSON is invalid or not an array of objects.
     */
    public static function decodeList(string $json): array
    {
        $data = self::decodeJson($json);

        if (!is_array($data) || !array_is_list($data)) {
            throw new UnexpectedResponseException('The response is not a JSON array.');
        }

        return self::objects($data, '');
    }

    /**
     * The decoded object, e.g. to keep a raw copy.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * True when the property is there and not null.
     */
    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * A non-empty string.
     */
    public function string(string $name): string
    {
        $value = $this->value($name);

        if (!is_string($value) || $value === '') {
            throw $this->invalid($name, 'a non-empty string');
        }

        return $value;
    }

    /**
     * A string that may be empty, null or left out; all give null.
     */
    public function nullableString(string $name): ?string
    {
        $value = $this->data[$name] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : throw $this->invalid($name, 'a string');
    }

    /**
     * A GUID such as `ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9`, in lower case.
     */
    public function uuid(string $name): string
    {
        $value = $this->value($name);

        if (!is_string($value) || !Guid::isValid($value)) {
            throw $this->invalid($name, 'a GUID');
        }

        return strtolower($value);
    }

    /**
     * Like uuid(), but null or left out gives null. The REST API also sends the empty GUID
     * `00000000-0000-0000-0000-000000000000` for "none"; that gives null as well.
     */
    public function nullableUuid(string $name): ?string
    {
        if (!$this->has($name)) {
            return null;
        }

        $uuid = $this->uuid($name);

        return $uuid === Guid::EMPTY ? null : $uuid;
    }

    public function int(string $name): int
    {
        $value = $this->value($name);

        return is_int($value) ? $value : throw $this->invalid($name, 'a whole number');
    }

    public function nullableInt(string $name): ?int
    {
        return $this->has($name) ? $this->int($name) : null;
    }

    public function bool(string $name): bool
    {
        $value = $this->value($name);

        return is_bool($value) ? $value : throw $this->invalid($name, 'true or false');
    }

    public function nullableBool(string $name): ?bool
    {
        return $this->has($name) ? $this->bool($name) : null;
    }

    /**
     * A JSON number as an exact string, e.g. `21`, `121.1` or `-0.5`.
     *
     * @return numeric-string
     */
    public function decimal(string $name): string
    {
        $value = $this->value($name);

        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_float($value) || !is_finite($value)) {
            throw $this->invalid($name, 'a number');
        }

        // 15 significant digits: every decimal with at most 15 digits survives the float.
        $text = sprintf('%.15G', $value);

        if (preg_match('~^-?\d+(\.\d+)?$~', $text) !== 1) {
            throw $this->invalid($name, 'a number without exponent');
        }

        return $text === '-0' ? '0' : $text;
    }

    /**
     * @return ?numeric-string
     */
    public function nullableDecimal(string $name): ?string
    {
        return $this->has($name) ? $this->decimal($name) : null;
    }

    /**
     * A money amount with at most two decimals.
     */
    public function amount(string $name): Amount
    {
        $text = $this->decimal($name);

        if (preg_match('~^-?\d+(\.\d{1,2})?$~', $text) !== 1) {
            throw $this->invalid($name, 'an amount with at most 2 decimals');
        }

        return Amount::of($text);
    }

    public function nullableAmount(string $name): ?Amount
    {
        return $this->has($name) ? $this->amount($name) : null;
    }

    /**
     * A date and time such as `2026-03-15T00:00:00` or `2026-03-15T10:12:45.123Z`. Without time zone, it
     * is in the time zone of PHP, like the other dates this library reads.
     */
    public function dateTime(string $name): DateTimeImmutable
    {
        $value = $this->value($name);

        $pattern = '~^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?)?$~';

        if (!is_string($value) || preg_match($pattern, $value) !== 1) {
            throw $this->invalid($name, 'a date and time');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw $this->invalid($name, 'a date and time');
        }
    }

    public function nullableDateTime(string $name): ?DateTimeImmutable
    {
        return $this->has($name) ? $this->dateTime($name) : null;
    }

    /**
     * A moment such as `createdAt` or `modified`, in UTC. The REST API sends them with `Z`, but not
     * always: the list of VAT codes leaves it out where the VAT code itself has it. Without a zone the
     * value is UTC as well.
     */
    public function timestamp(string $name): DateTimeImmutable
    {
        $dateTime = $this->dateTime($name);
        $value = $this->data[$name];
        $utc = new DateTimeZone('UTC');

        if (is_string($value) && preg_match('~(Z|[+-]\d{2}:\d{2})$~', $value) !== 1) {
            return new DateTimeImmutable($dateTime->format('Y-m-d\TH:i:s.u'), $utc);
        }

        return $dateTime->setTimezone($utc);
    }

    public function nullableTimestamp(string $name): ?DateTimeImmutable
    {
        return $this->has($name) ? $this->timestamp($name) : null;
    }

    /**
     * A date without time such as `startDate` or `investmentDate` (`2021-04-26T00:00:00`, sometimes
     * with `Z`), at midnight in the time zone of PHP, like the other dates this library reads.
     */
    public function date(string $name): DateTimeImmutable
    {
        return new DateTimeImmutable($this->dateTime($name)->format('Y-m-d'));
    }

    public function nullableDate(string $name): ?DateTimeImmutable
    {
        return $this->has($name) ? $this->date($name) : null;
    }

    /**
     * A value of a backed enum, e.g. `enum('status', FixedAssetStatus::class)`.
     *
     * @template E of BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return E
     */
    public function enum(string $name, string $enum): BackedEnum
    {
        $value = $this->value($name);
        $case = is_string($value) || is_int($value) ? $enum::tryFrom($value) : null;

        if ($case === null) {
            $values = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

            throw $this->invalid($name, 'one of ' . implode(', ', $values));
        }

        return $case;
    }

    /**
     * @template E of BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return ?E
     */
    public function nullableEnum(string $name, string $enum): ?BackedEnum
    {
        return $this->has($name) ? $this->enum($name, $enum) : null;
    }

    public function object(string $name): self
    {
        $value = $this->value($name);

        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->invalid($name, 'an object');
        }

        return new self($value, $this->childPath($name));
    }

    public function nullableObject(string $name): ?self
    {
        return $this->has($name) ? $this->object($name) : null;
    }

    /**
     * An array of objects. A missing or null array gives an empty list: the REST API leaves out
     * empty arrays.
     *
     * @return list<self>
     */
    public function list(string $name): array
    {
        if (!$this->has($name)) {
            return [];
        }

        $value = $this->data[$name];

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid($name, 'an array');
        }

        return self::objects($value, $this->childPath($name));
    }

    /**
     * An array of strings; missing or null gives an empty list, like list().
     *
     * @return list<string>
     */
    public function strings(string $name): array
    {
        if (!$this->has($name)) {
            return [];
        }

        $value = $this->data[$name];

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid($name, 'an array of strings');
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw $this->invalid($name, 'an array of strings');
            }
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * An object with string values, such as translations (`{"nl-NL": "Fiets"}`); missing or null gives
     * an empty array.
     *
     * @return array<string, string>
     */
    public function stringMap(string $name): array
    {
        if (!$this->has($name)) {
            return [];
        }

        $value = $this->data[$name];

        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->invalid($name, 'an object with strings');
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (!is_string($item)) {
                throw $this->invalid($name, 'an object with strings');
            }

            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * Exception for a value that is not what the SDK expects, e.g. `invalid('status', 'active or inactive')`.
     */
    public function invalid(string $name, string $expected): UnexpectedResponseException
    {
        $value = $this->data[$name] ?? null;
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return new UnexpectedResponseException(sprintf(
            '%s is %s, expected %s.',
            $this->childPath($name),
            $json === false ? 'invalid' : $json,
            $expected,
        ));
    }

    private function value(string $name): mixed
    {
        if (!array_key_exists($name, $this->data)) {
            throw new UnexpectedResponseException(sprintf('%s is missing.', $this->childPath($name)));
        }

        if ($this->data[$name] === null) {
            throw new UnexpectedResponseException(sprintf('%s is null.', $this->childPath($name)));
        }

        return $this->data[$name];
    }

    private function childPath(string $name): string
    {
        return $this->path === '' ? $name : $this->path . '/' . $name;
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<self>
     */
    private static function objects(array $items, string $path): array
    {
        $objects = [];

        foreach ($items as $index => $item) {
            $itemPath = sprintf('%s[%d]', $path, $index);

            if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                throw new UnexpectedResponseException(sprintf('%s is not a JSON object.', self::describe($itemPath)));
            }

            $objects[] = new self($item, $itemPath);
        }

        return $objects;
    }

    private static function decodeJson(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            throw new UnexpectedResponseException(
                sprintf('The response is not valid JSON: %s.', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    private static function describe(string $path): string
    {
        return $path === '' ? 'The response' : $path;
    }
}
