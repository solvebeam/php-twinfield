<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * The sales transaction Twinfield booked for a final sales invoice (`<financials>`).
 *
 *   $transaction = $twinfield->salesInvoices()->salesTransaction($invoice);
 */
final readonly class SalesInvoiceTransaction
{
    public function __construct(
        /** Day book (transaction type) code, e.g. `VRK`. */
        public string $dayBook,
        /** Transaction number, e.g. `202600012`. */
        public int $number,
    ) {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book must not be empty.');
        }
    }

    public static function fromXml(XmlElement $financials): self
    {
        return new self($financials->string('code'), $financials->int('number'));
    }
}
