<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use InvalidArgumentException;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An office (company) in the list and search results. Use `offices()->get($code)` for all
 * settings.
 *
 *   foreach ($twinfield->offices()->list() as $office) {
 *       echo $office->code, ' ', $office->name, ' ', $office->companyId, PHP_EOL;
 *   }
 */
final readonly class OfficeSummary
{
    public function __construct(
        /** Office code, e.g. `1000`; pass it to `$twinfield->company()`. */
        public string $code,
        public string $name,
        /** Unique ID of the company, e.g. `ffe2c8b3-e7f0-4793-a059-ffbfe3c154a9`. */
        public string $companyId,
        /** ISO 3166-1 alpha-2 country code, e.g. `NL`. */
        public string $country,
        /** VAT declaration period (`vatmanagement/decltimeframe` of the office), e.g. `1`. */
        public int $vatPeriod,
        /** Starting quarter of the VAT declarations (`vatmanagement/startingquarter`), e.g. `21`. */
        public int $startingQuarter,
    ) {
        $required = ['code' => $code, 'name' => $name, 'company ID' => $companyId, 'country' => $country];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Office %s must not be empty.', $field));
            }
        }
    }

    /**
     * From a Finder `OFF` row with option `includeid=1`; columns: code, name, company ID,
     * country, VAT period, starting quarter (column names depend on the user's language).
     *
     * @param list<string> $row
     */
    public static function fromFinderRow(array $row, int $index): self
    {
        if (count($row) !== 6) {
            throw self::invalidRow($index, $row, sprintf('has %d columns, expected 6', count($row)));
        }

        [$code, $name, $companyId, $country, $vatPeriod, $startingQuarter] = $row;

        if ($code === '' || $name === '' || $companyId === '' || $country === '') {
            throw self::invalidRow($index, $row, 'has no code, name, company ID or country');
        }

        if (!ctype_digit($vatPeriod) || !ctype_digit($startingQuarter)) {
            throw self::invalidRow($index, $row, 'has a VAT period or starting quarter that is not a number');
        }

        return new self($code, $name, $companyId, $country, (int) $vatPeriod, (int) $startingQuarter);
    }

    /**
     * @param list<string> $row
     */
    private static function invalidRow(int $index, array $row, string $problem): UnexpectedResponseException
    {
        return new UnexpectedResponseException(sprintf(
            'Finder office row %d %s: %s.',
            $index,
            $problem,
            json_encode($row, JSON_THROW_ON_ERROR),
        ));
    }
}
