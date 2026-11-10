<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A transaction line in a match set: the other side of a payment, e.g. the invoice it paid.
 */
final readonly class MatchLine
{
    public function __construct(
        /** Day book (transaction type) code, e.g. `VRK`. */
        public string $dayBook,
        public int $number,
        /** Line ID within that transaction. */
        public int $line,
        public Amount $matchValue,
    ) {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Match line day book must not be empty.');
        }
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        $method = $line->string('method');

        // Fixed value in the documentation.
        if ($method !== 'payment') {
            throw $line->child('method')->invalid('payment');
        }

        return new self(
            dayBook: $line->string('code'),
            number: $line->int('number'),
            line: $line->int('line'),
            matchValue: TransactionXml::amount($line, 'matchvalue'),
        );
    }
}
