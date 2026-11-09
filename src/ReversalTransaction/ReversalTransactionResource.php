<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ReversalTransaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\TransactionReference;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Reversals of journal transactions: read, reverse a final transaction, set the reversal of a
 * provisional one, and undo that.
 *
 *   $reversals = $twinfield->company('1000')->reversalTransactions();
 *
 *   $reversal = $reversals->get('MEMO', 202600003);
 *
 *   if (!$reversal->isReversed()) {
 *       $reversed = $reversals->postFinal('MEMO', 202600003);
 *   }
 *
 * Twinfield makes the reversed transaction itself, as a complete reverse of the original, in the
 * reversal day book of the journal (e.g. `MEMOTEGENBOEKING`). Read it with `journalTransactions()`.
 *
 * Twinfield refusals are thrown as ProcessXmlException, also when the transaction does not exist
 * or is not a journal transaction (`Originele transactie MEMO:202699999 is niet gevonden.`).
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/reversal-transactions/
 */
final readonly class ReversalTransactionResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * The reversal of a journal transaction; `reversed` is null when there is no reversed
     * transaction.
     *
     * Not documented: a deleted transaction is still found, with `reversed` null.
     *
     * @param string $dayBook Journal day book code, e.g. `MEMO`.
     *
     * @throws ProcessXmlException When there is no such journal transaction.
     */
    public function get(string $dayBook, int $number): Reversal
    {
        return $this->reversal($this->send('getreversetransactioninfo', $dayBook, $number));
    }

    /**
     * Reverses a final journal transaction: Twinfield creates the reversed transaction, posts it
     * as final and links it to the original. Returns the reversed transaction; `get()` has the
     * settings.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a provisional transaction.
     */
    public function postFinal(string $dayBook, int $number): TransactionReference
    {
        return $this->reversal($this->send('postfinal', $dayBook, $number))->reversed
            ?? throw new UnexpectedResponseException('reverse/reversed is missing after postfinal.');
    }

    /**
     * Saves the reversal settings of a provisional journal transaction; there is no reversed
     * transaction yet.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a final transaction.
     */
    public function postProvisional(string $dayBook, int $number): Reversal
    {
        return $this->reversal($this->send('postprovisional', $dayBook, $number));
    }

    /**
     * Removes the reversal settings of a provisional journal transaction.
     *
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function undo(string $dayBook, int $number): void
    {
        $this->send('undo', $dayBook, $number);
    }

    private function send(string $action, string $dayBook, int $number): XmlElement
    {
        if ($number < 1) {
            throw new InvalidArgumentException(sprintf('Transaction number must be 1 or more, got %d.', $number));
        }

        $company = $this->twinfield->companyReference();

        $xml = XmlBuilder::create('reverse', ['action' => $action]);
        $original = $xml->add($xml->root(), 'original');
        $xml->add($original, 'office', $company->code);
        $xml->add($original, 'code', XmlValue::code($dayBook));
        $xml->add($original, 'number', (string) $number);

        $reverse = XmlElement::root($this->twinfield->processXml($xml->toXml()));

        if ($reverse->element->localName !== 'reverse') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <reverse> response, got <%s>.',
                $reverse->element->localName,
            ));
        }

        $returned = TransactionReference::fromXml($reverse->child('original'), $company);

        if (!$returned->equals(new TransactionReference($company, $dayBook, $number))) {
            throw new UnexpectedResponseException(sprintf(
                'Asked for the reversal of %s %d in %s, got %s %d in %s.',
                $dayBook,
                $number,
                $company->code,
                $returned->dayBook,
                $returned->number,
                $returned->company->code,
            ));
        }

        return $reverse;
    }

    private function reversal(XmlElement $reverse): Reversal
    {
        return Reversal::fromXml($reverse, $this->twinfield->companyReference());
    }
}
