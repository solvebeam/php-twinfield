<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Customer;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * SEPA direct debit mandate of a customer (`financials/collectmandate`).
 */
final readonly class CollectMandate
{
    public function __construct(
        /** Mandate ID, at most 35 characters. Twinfield fills in the customer code by default. */
        public string $id,
        /** Date the customer signed the mandate; null when not set. */
        public ?DateTimeImmutable $signatureDate,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Mandate id must not be empty.');
        }
    }

    /**
     * From the `<collectmandate>` element, or null when it has no id.
     *
     * @internal
     */
    public static function fromXml(XmlElement $mandate): ?self
    {
        $id = $mandate->nullableString('id');
        $signatureDate = $mandate->nullableDate('signaturedate');

        if ($id === null && $signatureDate === null) {
            return null;
        }

        return new self($mandate->string('id'), $signatureDate);
    }
}
