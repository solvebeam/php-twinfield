<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesTransaction;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\OpenInvoiceSearch;
use SolveBeam\Twinfield\Transaction\SaveManyResult;
use SolveBeam\Twinfield\Transaction\TransactionClient;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Sales transactions of the company: read, create, update, post, delete, change the pay status
 * and search open invoices.
 *
 *   $sales = $twinfield->company('1000')->salesTransactions();
 *
 *   $transaction = $sales->get('VRK', 202600012);
 *
 *   $created = $sales->create(
 *       SalesTransactionInput::invoice('VRK', customer: '1000', invoiceNumber: '2026-0042', date: $date)
 *           ->dueDate($date->modify('+30 days'))
 *           ->revenue('8000', '100.00', vatCode: 'VH', vatValue: '21.00'),
 *   );
 *
 *   $sales->update($created->edit()->paymentReference('2026-0042'));
 *   $sales->post('VRK', $created->number());
 *
 *   $sales->excludeFromCollection('VRK', $created->number());
 *
 * Only provisional transactions can be changed, posted or deleted. Twinfield refusals are
 * thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final readonly class SalesTransactionResource
{
    /** @var TransactionClient<SalesTransaction> */
    private TransactionClient $client;

    public function __construct(
        private Twinfield $twinfield,
    ) {
        $this->client = new TransactionClient($twinfield, SalesTransaction::fromXml(...), 'Sales transactions');
    }

    /**
     * @param string $dayBook Sales day book code, e.g. `VRK`.
     *
     * @throws TransactionNotFoundException When there is no such transaction.
     */
    public function get(string $dayBook, int $number): SalesTransaction
    {
        return $this->client->get($dayBook, $number);
    }

    /**
     * The transaction, or null when it does not exist.
     */
    public function find(string $dayBook, int $number): ?SalesTransaction
    {
        return $this->client->find($dayBook, $number);
    }

    /**
     * Creates a new transaction; Twinfield gives it a number.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown customer or a VAT amount that differs.
     */
    public function create(SalesTransactionInput $input): SalesTransaction
    {
        return $this->client->create($input);
    }

    /**
     * Updates a provisional transaction with all values of the input. Start from
     * `$transaction->edit()` to keep the values you do not change.
     *
     * @throws TransactionNotFoundException When there is no transaction with this number.
     */
    public function update(SalesTransactionInput $input): SalesTransaction
    {
        return $this->client->update($input);
    }

    /**
     * Creates the transaction, or updates it when it has a number (one request, no check).
     */
    public function save(SalesTransactionInput $input): SalesTransaction
    {
        return $this->client->save($input);
    }

    /**
     * Saves several transactions in one request. Twinfield saves the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @return SaveManyResult<SalesTransaction>
     */
    public function saveMany(SalesTransactionInput ...$inputs): SaveManyResult
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

    /**
     * Includes the transaction in the collect process of Twinfield again (`changepaystatus`).
     */
    public function includeInCollection(string $dayBook, int $number): void
    {
        $this->client->changePayStatus($dayBook, $number, true);
    }

    /**
     * Excludes the transaction from the collect process of Twinfield (`changepaystatus` with
     * `paystatus` `N`).
     */
    public function excludeFromCollection(string $dayBook, int $number): void
    {
        $this->client->changePayStatus($dayBook, $number, false);
    }

    /**
     * Search open (unpaid) invoices, Finder type `IVT`, e.g. `openInvoices()->customer('1000')->get()`.
     *
     * Without customer() or account(), the results include open purchase invoices.
     */
    public function openInvoices(): OpenInvoiceSearch
    {
        return new OpenInvoiceSearch($this->twinfield->finder(FinderType::OpenInvoices));
    }
}
