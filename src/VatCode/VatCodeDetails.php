<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Guid;
use SolveBeam\Twinfield\Organisation\CompanyReference;

/**
 * A VAT code as the REST API returns it: with its GUID, its rates over time and the allocation rules
 * of each rate, which refer to accounts and VAT groups by GUID.
 *
 * `vatCodes()->get($code)` reads the same VAT code with the XML web services, with account codes.
 */
final readonly class VatCodeDetails
{
    /**
     * @param list<VatCodeTranslation> $translations
     * @param list<VatRate>            $rates
     */
    public function __construct(
        public CompanyReference $company,
        /** GUID of the VAT code. */
        public string $id,
        /** Code, e.g. `VH`. */
        public string $code,
        public VatCodeType $type,
        public string $name,
        public string $shortName,
        public ?string $invoiceText,
        public array $translations,
        public array $rates,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastModifiedAt,
        /** Code of the user who last changed it. */
        public string $modifiedBy,
    ) {
        Guid::of($id);

        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('VAT code and name must not be empty.');
        }
    }

    /**
     * The rate on a date: the one with the latest start date on or before it; null before the first.
     */
    public function rateOn(\DateTimeInterface $date): ?VatRate
    {
        $current = null;

        foreach ($this->rates as $rate) {
            if ($rate->startDate <= $date && ($current === null || $rate->startDate > $current->startDate)) {
                $current = $rate;
            }
        }

        return $current;
    }
}
