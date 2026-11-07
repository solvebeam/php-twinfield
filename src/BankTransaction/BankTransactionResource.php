<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BankTransaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\SaveManyResult;
use SolveBeam\Twinfield\Transaction\TransactionClient;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Bank transactions of the company: read, create, update, post and delete.
 *
 *   $bank = $twinfield->company('1000')->bankTransactions();
 *
 *   $transaction = $bank->get('BNK', 202600045);
 *
 *   $created = $bank->create(
 *       BankTransactionInput::create('BNK', '1010', new DateTimeImmutable('2026-11-03'))
 *           ->receive('1300', '121.00', customer: '1000', invoice: '2026-0042'),
 *   );
 *
 *   $bank->update($created->edit()->description('Statement 245'));
 *   $bank->post('BNK', $created->number());
 *   $bank->delete('BNK', $created->number(), 'Imported twice');
 *
 * Only provisional transactions can be changed, posted or deleted. Twinfield refusals are
 * thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/
 */
final readonly class BankTransactionResource
{
    /** @var TransactionClient<BankTransaction> */
    private TransactionClient $client;

    public function __construct(Twinfield $twinfield)
    {
        $this->client = new TransactionClient($twinfield, BankTransaction::fromXml(...), 'Bank transactions');
    }

    /**
     * @param string $dayBook Bank book code, e.g. `BNK`.
     *
     * @throws TransactionNotFoundException When there is no such transaction.
     */
    public function get(string $dayBook, int $number): BankTransaction
    {
        return $this->client->get($dayBook, $number);
    }

    /**
     * The transaction, or null when it does not exist.
     */
    public function find(string $dayBook, int $number): ?BankTransaction
    {
        return $this->client->find($dayBook, $number);
    }

    /**
     * Creates a new transaction; Twinfield gives it a number.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown customer.
     */
    public function create(BankTransactionInput $input): BankTransaction
    {
        return $this->client->create($input);
    }

    /**
     * Updates a provisional transaction with all values of the input. Start from
     * `$transaction->edit()` to keep the values you do not change.
     *
     * @throws TransactionNotFoundException When there is no transaction with this number.
     */
    public function update(BankTransactionInput $input): BankTransaction
    {
        return $this->client->update($input);
    }

    /**
     * Creates the transaction, or updates it when it has a number (one request, no check).
     */
    public function save(BankTransactionInput $input): BankTransaction
    {
        return $this->client->save($input);
    }

    /**
     * Saves several transactions in one request. Twinfield saves the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @return SaveManyResult<BankTransaction>
     */
    public function saveMany(BankTransactionInput ...$inputs): SaveManyResult
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
