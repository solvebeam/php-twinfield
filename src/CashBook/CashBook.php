<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashBook;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;

/**
 * A cash book as Twinfield returns it (CashBookService GetCashBook).
 *
 *   $cashBook = $twinfield->cashBooks()->get('KAS');
 *   echo $cashBook->name, ' ', $cashBook->generalLedgerAccount ?? '-', PHP_EOL;
 *
 * To change it, call edit() and pass the result to update(). To book on it, pass it to
 * `CashTransactionInput::forCashBook()` and create it with `$twinfield->cashTransactions()`.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/cash-book/
 */
final readonly class CashBook
{
    public function __construct(
        /** Company (office) code. */
        public string $company,
        /** Code of the cash book (a transaction type), e.g. `KAS`. */
        public string $code,
        public string $name,
        /** Null when not set. */
        public ?string $shortName,
        /** Balance sheet account of the cash, e.g. `1000`. Null when not set. */
        public ?string $generalLedgerAccount,
    ) {
        foreach (['company' => $company, 'code' => $code, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Cash book %s must not be empty.', $field));
            }
        }
    }

    /**
     * From a `GetCashBookResult`. The result has no code: it is the code of the request.
     */
    public static function fromResult(ResultElement $result, string $company, string $code): self
    {
        return new self(
            company: $company,
            code: $code,
            name: $result->string('Name'),
            // Empty elements when not set.
            shortName: $result->nullableString('ShortName'),
            generalLedgerAccount: $result->nullableString('GeneralLedgerAccount'),
        );
    }

    /**
     * Input with all values of this cash book, to change and pass to update().
     *
     *   $cashBooks->update($cashBook->edit()->name('Kas winkel'));
     */
    public function edit(): CashBookInput
    {
        return CashBookInput::create($this->code, $this->name)
            ->shortName($this->shortName)
            ->generalLedgerAccount($this->generalLedgerAccount);
    }
}
