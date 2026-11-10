<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Fixtures;

use RuntimeException;
use stdClass;

/**
 * Replaces every value in a REST API response (JSON) by a fake value with the same format, with the
 * rules of XmlAnonymizer: GUIDs stay GUIDs, dates stay dates, digits stay digits, and the same input
 * always gives the same output.
 *
 * - Property names and the structure stay the same.
 * - Numbers keep their digits format (`121.10` stays a number with two decimals); numbers up to 99
 *   are kept, like in XML.
 * - URLs keep their path, with `https://example.test` as host and the GUIDs in it replaced.
 * - Values of the KEEP_KEYS properties are kept (enums, not data).
 */
final readonly class JsonAnonymizer
{
    /** Properties whose (string) value is kept as is: enums and fixed values. */
    private const KEEP_KEYS = [
        'status', 'type', 'regime', 'culture', 'cultureCode', 'unitOfMeasure', 'rel', 'method', 'balanceType',
        'searchScope', 'action', 'actionType', 'scope', 'state', 'reportStatus', 'lifetimeType',
        'depreciationMethod', 'subanalysis', 'activationStatus', 'currency', 'language', 'fixedAssetAction',
        'spreadFor',
    ];

    /** Properties whose number is kept as is: counts, versions and periods, not data. */
    private const KEEP_NUMBER_KEYS = [
        'total', 'filteredTotal', 'version', 'year', 'period', 'periodNumber', 'periodFrom', 'month', 'day',
        'weight', 'startYear', 'targetYear', 'matchLevel', 'yearFrom',
    ];

    public function __construct(
        private XmlAnonymizer $values,
    ) {
    }

    public function anonymize(string $json): string
    {
        $data = json_decode($json, false, 512, JSON_BIGINT_AS_STRING);

        if (!is_array($data) && !is_object($data)) {
            throw new RuntimeException('Cannot anonymize: input is not a JSON object or array.');
        }

        // Numbers are written back with their exact digits: decoded as float they could change.
        [$preserved, $numbers] = self::quoteNumbers($json);

        // Objects stay objects, so an empty object stays `{}`.
        $data = json_decode($preserved, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        $anonymized = $this->walk($data, null, $numbers);

        $encoded = json_encode($anonymized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return (string) preg_replace('~"@number:(-?\d+(?:\.\d+)?)@"~', '$1', $encoded) . "\n";
    }

    /**
     * Replaces each number outside strings by a placeholder string `"@n0@"`, `"@n1@"`, ….
     *
     * @return array{string, list<string>}
     */
    private static function quoteNumbers(string $json): array
    {
        $result = '';
        $numbers = [];
        $length = strlen($json);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $character = $json[$i];

            if ($inString) {
                $result .= $character;

                if ($character === '\\') {
                    $result .= $json[++$i] ?? '';
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
                $result .= $character;

                continue;
            }

            if (preg_match('~-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?~A', $json, $matches, 0, $i) === 1 && ($character === '-' || ctype_digit($character))) {
                $numbers[] = $matches[0];
                $result .= sprintf('"@n%d@"', count($numbers) - 1);
                $i += strlen($matches[0]) - 1;

                continue;
            }

            $result .= $character;
        }

        return [$result, $numbers];
    }

    /**
     * @param list<string> $numbers
     */
    private function walk(mixed $value, ?string $key, array $numbers): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->walk($item, $key, $numbers), $value);
        }

        if ($value instanceof stdClass) {
            $result = new stdClass();

            foreach (get_object_vars($value) as $childKey => $child) {
                $result->{$childKey} = $this->walk($child, (string) $childKey, $numbers);
            }

            return $result;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('~^@n(\d+)@$~', $value, $matches) === 1) {
            $number = $numbers[(int) $matches[1]];

            if ($key !== null && in_array($key, self::KEEP_NUMBER_KEYS, true)) {
                $this->values->keepValue($number);

                return '@number:' . $number . '@';
            }

            // A JSON number can't start with 0 followed by a digit (`04.41`).
            $fake = (string) preg_replace('~^(-?)0(?=\d)~', '${1}1', $this->values->anonymizeValue($number));

            return '@number:' . $fake . '@';
        }

        // Culture codes such as `nl-NL` are not data.
        if (($key !== null && in_array($key, self::KEEP_KEYS, true)) || preg_match('~^[a-z]{2}-[A-Z]{2}$~', $value) === 1) {
            $this->values->keepValue($value);

            return $value;
        }

        if (preg_match('~^https?://[^/]+(/.*)?$~i', $value, $matches) === 1) {
            $path = preg_replace_callback(
                '~[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}~i',
                fn (array $guid): string => $this->values->anonymizeValue($guid[0], force: true),
                $matches[1] ?? '',
            );

            return 'https://example.test' . $path;
        }

        return $this->values->anonymizeValue($value);
    }
}
