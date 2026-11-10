<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DOMElement;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;

/**
 * A transaction to create or update, of any kind (bank, cash, …).
 */
interface TransactionInput
{
    /**
     * Day book (transaction type) code, e.g. `BNK`.
     */
    public function dayBook(): string;

    /**
     * Null for a new transaction: Twinfield gives it a number.
     */
    public function transactionNumber(): ?int;

    /**
     * Adds the `<transaction>` element to the parent (a `<transactions>` element).
     *
     * @internal
     */
    public function appendTo(XmlBuilder $xml, DOMElement $parent, string $company): void;

    /**
     * The `<transaction>` request on its own.
     *
     * @internal
     */
    public function toXml(string $company): XmlBuilder;
}
