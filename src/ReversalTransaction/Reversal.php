<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ReversalTransaction;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\TransactionReference;

/**
 * The reversal of a journal transaction: the original transaction, the reversed transaction
 * Twinfield made for it (a complete reverse, in the reversal day book, e.g. `MEMOTEGENBOEKING`)
 * and the reversal settings.
 */
final readonly class Reversal
{
    public function __construct(
        public TransactionReference $original,
        /**
         * The reversed transaction, or null when there is none (yet): Twinfield leaves
         * `<reversed>` out then, e.g. for a provisional transaction with only settings.
         */
        public ?TransactionReference $reversed,
        /** The reversed transaction is (to be) posted in the period after the original one. */
        public bool $reverseInNextPeriod,
    ) {
    }

    /**
     * From the `<reverse>` response of `getreversetransactioninfo`, `postfinal` or `postprovisional`.
     */
    public static function fromXml(XmlElement $reverse, CompanyReference $company): self
    {
        $reversed = $reverse->nullableChild('reversed');

        return new self(
            original: TransactionReference::fromXml($reverse->child('original'), $company),
            reversed: $reversed === null ? null : TransactionReference::fromXml($reversed, $company),
            reverseInNextPeriod: $reverse->child('settings')->bool('reverseinnextperiod'),
        );
    }

    public function isReversed(): bool
    {
        return $this->reversed !== null;
    }
}
