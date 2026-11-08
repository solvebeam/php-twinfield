<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A pair of system dimensions for debit and credit amounts, e.g. the accounts for exchange
 * differences. Each one is null when not set.
 */
final readonly class DebitCredit
{
    public function __construct(
        public ?SystemDimension $debit,
        public ?SystemDimension $credit,
    ) {
    }

    public static function fromXml(XmlElement $element): self
    {
        return new self(
            SystemDimension::nullable($element->child('debit')),
            SystemDimension::nullable($element->child('credit')),
        );
    }
}
