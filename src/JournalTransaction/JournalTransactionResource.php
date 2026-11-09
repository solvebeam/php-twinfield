<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\JournalTransaction;

use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\SaveManyResult;
use SolveBeam\Twinfield\Transaction\TransactionClient;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Journal transactions of the company: read, create, update, post and delete.
 *
 *   $journal = $twinfield->company('1000')->journalTransactions();
 *
 *   $transaction = $journal->get('MEMO', 202600003);
 *
 *   $created = $journal->create(
 *       JournalTransactionInput::create('MEMO', $date)
 *           ->debit('4008', '435.55')
 *           ->credit('1300', '435.55', customer: '1000', invoice: '11001770', description: 'Invoice paid'),
 *   );
 *
 *   $journal->update($created->edit()->freeText(1, 'Correction'));
 *   $journal->post('MEMO', $created->number());
 *
 * Only provisional transactions can be changed, posted or deleted. Twinfield refusals are
 * thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/journal-transactions/
 */
final readonly class JournalTransactionResource
{
    /** @var TransactionClient<JournalTransaction> */
    private TransactionClient $client;

    public function __construct(Twinfield $twinfield)
    {
        $this->client = new TransactionClient($twinfield, JournalTransaction::fromXml(...), 'Journal transactions');
    }

    /**
     * @param string $dayBook Journal day book code, e.g. `MEMO`.
     *
     * @throws TransactionNotFoundException When there is no such transaction, or it was deleted.
     */
    public function get(string $dayBook, int $number): JournalTransaction
    {
        return $this->client->get($dayBook, $number);
    }

    /**
     * The transaction, or null when it does not exist or was deleted.
     */
    public function find(string $dayBook, int $number): ?JournalTransaction
    {
        return $this->client->find($dayBook, $number);
    }

    /**
     * Creates a new transaction; Twinfield gives it a number.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown account or a period that is closed.
     */
    public function create(JournalTransactionInput $input): JournalTransaction
    {
        return $this->client->create($input);
    }

    /**
     * Updates a provisional transaction with all values of the input. Start from
     * `$transaction->edit()` to keep the values you do not change.
     *
     * @throws TransactionNotFoundException When there is no transaction with this number.
     */
    public function update(JournalTransactionInput $input): JournalTransaction
    {
        return $this->client->update($input);
    }

    /**
     * Creates the transaction, or updates it when it has a number (one request, no check).
     */
    public function save(JournalTransactionInput $input): JournalTransaction
    {
        return $this->client->save($input);
    }

    /**
     * Saves several transactions in one request. Twinfield saves the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @return SaveManyResult<JournalTransaction>
     */
    public function saveMany(JournalTransactionInput ...$inputs): SaveManyResult
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
