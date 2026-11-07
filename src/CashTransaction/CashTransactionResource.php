<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CashTransaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\SaveManyResult;
use SolveBeam\Twinfield\Transaction\TransactionClient;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Cash transactions of the company: read, create, update, post and delete.
 *
 *   $cash = $twinfield->company('1000')->cashTransactions();
 *
 *   $transaction = $cash->get('KAS', 202600045);
 *
 *   $created = $cash->create(
 *       CashTransactionInput::create('KAS', '1000', new DateTimeImmutable('2026-11-03'))
 *           ->receive('8000', '41.32', vatCode: 'VH', vatValue: '8.68'),
 *   );
 *
 *   $cash->update($created->edit()->description('Cash register 3 November'));
 *   $cash->post('KAS', $created->number());
 *   $cash->delete('KAS', $created->number(), 'Entered twice');
 *
 * Only provisional transactions can be changed, posted or deleted. Twinfield refusals are
 * thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/cash-transactions/
 */
final readonly class CashTransactionResource
{
    /** @var TransactionClient<CashTransaction> */
    private TransactionClient $client;

    public function __construct(Twinfield $twinfield)
    {
        $this->client = new TransactionClient($twinfield, CashTransaction::fromXml(...), 'Cash transactions');
    }

    /**
     * @param string $dayBook Cash book code, e.g. `KAS`.
     *
     * @throws TransactionNotFoundException When there is no such transaction.
     */
    public function get(string $dayBook, int $number): CashTransaction
    {
        return $this->client->get($dayBook, $number);
    }

    /**
     * The transaction, or null when it does not exist.
     */
    public function find(string $dayBook, int $number): ?CashTransaction
    {
        return $this->client->find($dayBook, $number);
    }

    /**
     * Creates a new transaction; Twinfield gives it a number.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown customer.
     */
    public function create(CashTransactionInput $input): CashTransaction
    {
        return $this->client->create($input);
    }

    /**
     * Updates a provisional transaction with all values of the input. Start from
     * `$transaction->edit()` to keep the values you do not change.
     *
     * @throws TransactionNotFoundException When there is no transaction with this number.
     */
    public function update(CashTransactionInput $input): CashTransaction
    {
        return $this->client->update($input);
    }

    /**
     * Creates the transaction, or updates it when it has a number (one request, no check).
     */
    public function save(CashTransactionInput $input): CashTransaction
    {
        return $this->client->save($input);
    }

    /**
     * Saves several transactions in one request. Twinfield saves the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @return SaveManyResult<CashTransaction>
     */
    public function saveMany(CashTransactionInput ...$inputs): SaveManyResult
    {
        return $this->client->saveMany(...$inputs);
    }

    /**
     * Posts a provisional transaction: it becomes final and can no longer be changed or deleted.
     */
    public function post(string $dayBook, int $number): void
    {
        $this->client->post($dayBook, $number);
    }

    /**
     * Deletes a provisional transaction.
     */
    public function delete(string $dayBook, int $number, string $reason): void
    {
        $this->client->delete($dayBook, $number, $reason);
    }
}
