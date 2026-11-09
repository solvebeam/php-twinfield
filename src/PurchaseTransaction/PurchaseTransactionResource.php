<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PurchaseTransaction;

use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\Transaction\OpenInvoiceSearch;
use SolveBeam\Twinfield\Transaction\SaveManyResult;
use SolveBeam\Twinfield\Transaction\TransactionClient;
use SolveBeam\Twinfield\Transaction\TransactionNotFoundException;
use SolveBeam\Twinfield\Twinfield;

/**
 * Purchase transactions of the company: read, create, update, post, delete, change the pay
 * status and search open invoices.
 *
 *   $purchases = $twinfield->company('1000')->purchaseTransactions();
 *
 *   $transaction = $purchases->get('INK', 202600021);
 *
 *   $created = $purchases->create(
 *       PurchaseTransactionInput::invoice('INK', supplier: '2000', invoiceNumber: 'F-2026-118', date: $date)
 *           ->dueDate($date->modify('+14 days'))
 *           ->expense('7000', '100.00', vatCode: 'IH', vatValue: '21.00'),
 *   );
 *
 *   $purchases->update($created->edit()->paymentReference('1234567890123456'));
 *   $purchases->post('INK', $created->number());
 *
 *   $purchases->excludeFromPaymentRuns('INK', $created->number());
 *
 * Only provisional transactions can be changed, posted or deleted. Twinfield refusals are
 * thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/purchase-transactions/
 */
final readonly class PurchaseTransactionResource
{
    /** @var TransactionClient<PurchaseTransaction> */
    private TransactionClient $client;

    public function __construct(
        private Twinfield $twinfield,
    ) {
        $this->client = new TransactionClient($twinfield, PurchaseTransaction::fromXml(...), 'Purchase transactions');
    }

    /**
     * @param string $dayBook Purchase day book code, e.g. `INK` (`PNV` in English companies).
     *
     * @throws TransactionNotFoundException When there is no such transaction.
     */
    public function get(string $dayBook, int $number): PurchaseTransaction
    {
        return $this->client->get($dayBook, $number);
    }

    /**
     * The transaction, or null when it does not exist.
     */
    public function find(string $dayBook, int $number): ?PurchaseTransaction
    {
        return $this->client->find($dayBook, $number);
    }

    /**
     * Creates a new transaction; Twinfield gives it a number.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown supplier or a VAT amount that differs.
     */
    public function create(PurchaseTransactionInput $input): PurchaseTransaction
    {
        return $this->client->create($input);
    }

    /**
     * Updates a provisional transaction with all values of the input. Start from
     * `$transaction->edit()` to keep the values you do not change.
     *
     * @throws TransactionNotFoundException When there is no transaction with this number.
     */
    public function update(PurchaseTransactionInput $input): PurchaseTransaction
    {
        return $this->client->update($input);
    }

    /**
     * Creates the transaction, or updates it when it has a number (one request, no check).
     */
    public function save(PurchaseTransactionInput $input): PurchaseTransaction
    {
        return $this->client->save($input);
    }

    /**
     * Saves several transactions in one request. Twinfield saves the valid ones, so this does
     * not throw when some fail: check the result.
     *
     * @return SaveManyResult<PurchaseTransaction>
     */
    public function saveMany(PurchaseTransactionInput ...$inputs): SaveManyResult
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
     * Includes the transaction in the payment runs of Twinfield again (`changepaystatus`).
     */
    public function includeInPaymentRuns(string $dayBook, int $number): void
    {
        $this->client->changePayStatus($dayBook, $number, true);
    }

    /**
     * Excludes the transaction from the payment runs of Twinfield (`changepaystatus` with
     * `paystatus` `N`).
     */
    public function excludeFromPaymentRuns(string $dayBook, int $number): void
    {
        $this->client->changePayStatus($dayBook, $number, false);
    }

    /**
     * Search open (unpaid) invoices, Finder type `IVT`, e.g. `openInvoices()->supplier('2000')->get()`.
     *
     * Without supplier() or account(), the results include open sales invoices.
     */
    public function openInvoices(): OpenInvoiceSearch
    {
        return new OpenInvoiceSearch($this->twinfield->finder(FinderType::OpenInvoices));
    }
}
