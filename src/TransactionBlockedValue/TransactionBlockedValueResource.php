<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\TransactionBlockedValue;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\CommandQuery\Command;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Twinfield;

/**
 * Blocked values of transaction lines, via the TransactionBlockedValueService (a Command & Query web service).
 *
 *   $blocked = $twinfield->company('1000')->transactionBlockedValues();
 *
 *   $blocked->register('INK', 202600021, line: 1, value: '2000.00');
 *   $blocked->unregister('INK', 202600021, line: 1);
 *
 * The line is the `id` of a line of the transaction, e.g. `TotalLine::$id` or `DetailLine::$id`.
 * The service only has these two commands: a blocked value cannot be read back through it.
 *
 * Errors are thrown as CommandQueryFaultException; `errorCode` holds a TransactionBlockedValueServiceFaultCode value.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/transaction-blocked-value/
 */
final readonly class TransactionBlockedValueResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Associates a blocked value with a line of a transaction.
     *
     * @param string $dayBook Day book code (Twinfield calls it the transaction code), e.g. `INK` or `MEMO`.
     * @param int $number Transaction number, e.g. 202600021.
     * @param int $line Line id within the transaction, from 1.
     * @param Amount|numeric-string $value E.g. `'2000.00'`; at most two decimals.
     */
    public function register(string $dayBook, int $number, int $line, Amount|string $value): void
    {
        self::validate($dayBook, $number, $line);

        // Field order follows the WSDL (alphabetical); the documentation example uses another order.
        $this->twinfield->process('TransactionBlockedValueService', new Command('RegisterBlockedAmountForTransaction', [
            'BlockedValue' => Amount::of($value)->value,
            'CompanyCode' => $this->companyCode(),
            'TransactionCode' => $dayBook,
            'TransactionLineId' => $line,
            'TransactionNumber' => $number,
        ]));
    }

    /**
     * Removes the blocked value from a line of a transaction.
     *
     * @param string $dayBook Day book code (Twinfield calls it the transaction code), e.g. `INK` or `MEMO`.
     * @param int $number Transaction number, e.g. 202600021.
     * @param int $line Line id within the transaction, from 1.
     */
    public function unregister(string $dayBook, int $number, int $line): void
    {
        self::validate($dayBook, $number, $line);

        // Field order follows the WSDL (alphabetical).
        $command = new Command('UnregisterBlockedAmountForTransaction', [
            'CompanyCode' => $this->companyCode(),
            'TransactionCode' => $dayBook,
            'TransactionLineId' => $line,
            'TransactionNumber' => $number,
        ]);

        $this->twinfield->process('TransactionBlockedValueService', $command);
    }

    /**
     * Twinfield reads the company from the command, so it is always the company of the client.
     */
    private function companyCode(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Command & Query web services need a company: use company() first.');
    }

    private static function validate(string $dayBook, int $number, int $line): void
    {
        if ($dayBook === '') {
            throw new InvalidArgumentException('Day book code must not be empty.');
        }

        if ($number < 1) {
            throw new InvalidArgumentException(sprintf('Transaction number must be positive, got %d.', $number));
        }

        if ($line < 1) {
            throw new InvalidArgumentException(sprintf('Transaction line id must be positive, got %d.', $line));
        }
    }
}
