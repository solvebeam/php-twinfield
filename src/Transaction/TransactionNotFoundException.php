<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DOMElement;
use RuntimeException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;

/**
 * The transaction does not exist in the company, e.g. `BNK` 202699999.
 *
 * Also thrown when the day book does not exist, because the transaction then cannot exist, and
 * when the transaction was deleted: see deleted().
 */
final class TransactionNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $dayBook,
        public readonly int $number,
        /** Message of Twinfield, in the language of the user. */
        public readonly string $reason,
        ?ProcessXmlException $previous = null,
        /** The transaction existed but was deleted. */
        public readonly bool $deleted = false,
    ) {
        parent::__construct(sprintf('Transaction %s %d not found: %s', $dayBook, $number, $reason), 0, $previous);
    }

    /**
     * The transaction was deleted. Twinfield still returns it, with `location="deleted"`, the
     * header and no lines; `$twinfield->deletedTransactions()` has the reason and who deleted it.
     */
    public static function deleted(string $dayBook, int $number): self
    {
        return new self($dayBook, $number, 'the transaction was deleted.', deleted: true);
    }

    /**
     * The not-found exception when Twinfield refused a read because the transaction does not
     * exist, or null for any other error.
     *
     * Twinfield then answers with an empty transaction with the error on it, e.g.
     * `<transaction msgtype="error" msg="De boeking bestaat niet." result="0" />`. The
     * message is in the language of the user, so the structure decides, not the text.
     */
    public static function fromProcessXml(ProcessXmlException $exception, string $dayBook, int $number): ?self
    {
        $result = $exception->result;
        $root = $result->root();
        $errors = $result->errors();

        $hasLines = false;

        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'lines') {
                $hasLines = true;
            }
        }

        $isNotFound = $root->localName === 'transaction' && !$hasLines && $errors !== [];

        return $isNotFound ? new self($dayBook, $number, $errors[0]->text, $exception) : null;
    }
}
