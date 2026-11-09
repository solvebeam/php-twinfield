<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use Generator;
use SolveBeam\Twinfield\Finder\CodeNameSearchResult;
use SolveBeam\Twinfield\Finder\FinderQuery;

/**
 * Immutable search for open (unpaid) invoices, Finder type `IVT`:
 *
 *   $page = $twinfield->salesTransactions()->openInvoices()
 *       ->customer('1000')
 *       ->get();
 *
 *   foreach ($twinfield->salesTransactions()->openInvoices()->pattern('2026-*')->all() as $invoice) {
 *       echo $invoice->invoiceNumber, ' ', $invoice->amount, PHP_EOL;
 *   }
 *
 * Without customer() or account(), the Finder also gives open purchase invoices. Every method
 * returns a new search, so a base search can be reused.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-transactions/
 */
final readonly class OpenInvoiceSearch
{
    public function __construct(
        private FinderQuery $query,
    ) {
    }

    /**
     * Search pattern on customer code, name, outstanding amount and invoice number; `*` matches anything,
     * `?` one character. Default `*`.
     */
    public function pattern(string $pattern): self
    {
        return new self($this->query->pattern($pattern));
    }

    /**
     * Only invoices of this customer (or supplier): Finder option `dim2`.
     */
    public function customer(string $code): self
    {
        TransactionXmlWriter::dimension('customer (dim2)', $code);

        return new self($this->query->option('dim2', $code));
    }

    /**
     * Only invoices on this accounts receivable account, e.g. `1300`: Finder option `dim1`. This
     * leaves out the purchase invoices.
     */
    public function account(string $code): self
    {
        TransactionXmlWriter::dimension('account (dim1)', $code);

        return new self($this->query->option('dim1', $code));
    }

    /**
     * Only invoices with this original amount: Finder option `value`.
     *
     * @param Amount|numeric-string $value
     */
    public function value(Amount|string $value): self
    {
        return new self($this->query->option('value', Amount::of($value)->value));
    }

    /**
     * Only invoices with this outstanding amount: Finder option `openvalue`.
     *
     * @param Amount|numeric-string $value
     */
    public function openValue(Amount|string $value): self
    {
        return new self($this->query->option('openvalue', Amount::of($value)->value));
    }

    /**
     * Results per page (default 100).
     */
    public function limit(int $limit): self
    {
        return new self($this->query->limit($limit));
    }

    /**
     * Number of results to skip (default 0).
     */
    public function offset(int $offset): self
    {
        return new self($this->query->offset($offset));
    }

    /**
     * One page of results.
     *
     * @return CodeNameSearchResult<OpenInvoice>
     */
    public function get(): CodeNameSearchResult
    {
        $company = $this->query->companyReference();
        $page = $this->query->get();

        return new CodeNameSearchResult(
            $page->totalRows,
            array_map(
                static fn (array $row, int $index): OpenInvoice => OpenInvoice::fromFinderRow($row, $index, $company),
                $page->rows,
                array_keys($page->rows),
            ),
        );
    }

    /**
     * All results over all pages, fetched page by page while you iterate.
     *
     * @return Generator<int, OpenInvoice>
     */
    public function all(): Generator
    {
        $company = $this->query->companyReference();

        foreach ($this->query->all() as $index => $row) {
            yield $index => OpenInvoice::fromFinderRow($row, $index, $company);
        }
    }

    /**
     * Number of matching invoices, with one small request.
     */
    public function count(): int
    {
        return $this->query->limit(1)->get()->totalRows;
    }
}
