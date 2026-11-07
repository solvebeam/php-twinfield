<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Performance details of a line with an ICT VAT code (intra-community supplies).
 *
 *   Performance::services(new DateTimeImmutable('2026-11-03'))->country('DE')->vatNumber('DE123456789');
 *   Performance::goods();
 *
 * Twinfield takes the country and VAT number of the customer when you leave them out.
 */
final readonly class Performance
{
    private function __construct(
        public PerformanceType $type,
        /** ISO country code, e.g. `DE`; `EL` for Greece and `XI` for Northern Ireland. */
        public ?string $country,
        public ?string $vatNumber,
        /** Only for services. */
        public ?DateTimeImmutable $date,
    ) {
        if ($country !== null && preg_match('~^[A-Z]{2}$~', $country) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a country code of 2 capitals, e.g. "DE".', $country),
            );
        }

        if ($vatNumber !== null) {
            XmlValue::maxLength('performance VAT number', $vatNumber, 16);
        }

        if ($type === PerformanceType::Goods && $date !== null) {
            throw new InvalidArgumentException('A performance date is only allowed for services.');
        }
    }

    /**
     * @param ?DateTimeInterface $date Performance date; mandatory for services with an ICT VAT code.
     */
    public static function services(?DateTimeInterface $date): self
    {
        return new self(PerformanceType::Services, null, null, self::immutable($date));
    }

    public static function goods(): self
    {
        return new self(PerformanceType::Goods, null, null, null);
    }

    public static function create(
        PerformanceType $type,
        ?string $country = null,
        ?string $vatNumber = null,
        ?DateTimeInterface $date = null,
    ): self {
        return new self($type, $country, $vatNumber, self::immutable($date));
    }

    public function country(?string $country): self
    {
        return new self($this->type, $country, $this->vatNumber, $this->date);
    }

    public function vatNumber(?string $vatNumber): self
    {
        return new self($this->type, $this->country, $vatNumber === '' ? null : $vatNumber, $this->date);
    }

    private static function immutable(?DateTimeInterface $date): ?DateTimeImmutable
    {
        return $date === null ? null : DateTimeImmutable::createFromInterface($date);
    }
}
