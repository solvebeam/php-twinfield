<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\SalesTransaction\SalesTransaction;
use SolveBeam\Twinfield\Twinfield;

/**
 * Sales invoices of the Classic invoicing module: Twinfield makes the invoice from articles,
 * calculates the amounts and VAT, and books the sales transaction when the invoice is final.
 *
 *   $invoices = $twinfield->company('1000')->salesInvoices();
 *
 *   $input = SalesInvoiceInput::create('FACTUUR', customer: '1000')
 *       ->paymentMethod(PaymentMethod::Bank)
 *       ->oneOff('Consultancy November', '950.00', vatCode: 'VH');
 *
 *   echo $invoices->calculate($input)->totals->valueInc, PHP_EOL; // nothing saved
 *
 *   $invoice = $invoices->create($input);                          // concept
 *   $invoice = $invoices->update($invoice->edit()->footerText('Thank you!'));
 *   $invoice = $invoices->finalize('FACTUUR', $invoice->invoiceNumber);
 *
 *   $transaction = $invoices->salesTransaction($invoice);          // the VRK booking
 *
 * Use a sales transaction (`salesTransactions()`) instead when your own software makes the
 * invoice. There is no web service for the new invoicing module (the blue tiles), and sales
 * invoices can't be deleted through the API. Twinfield refusals are thrown as
 * ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/
 */
final readonly class SalesInvoiceResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * @param string $invoiceType Invoice type code, e.g. `FACTUUR`.
     *
     * @throws SalesInvoiceNotFoundException When there is no such invoice, or no such invoice type.
     */
    public function get(string $invoiceType, int $invoiceNumber): SalesInvoice
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'salesinvoice');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $invoiceType);
        $xml->add($xml->root(), 'invoicenumber', (string) $invoiceNumber);

        try {
            $result = $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw SalesInvoiceNotFoundException::fromProcessXml($exception, $invoiceType, $invoiceNumber) ?? $exception;
        }

        return SalesInvoice::fromXml(XmlElement::root($result), $this->twinfield->organisation());
    }

    /**
     * The invoice, or null when it does not exist.
     */
    public function find(string $invoiceType, int $invoiceNumber): ?SalesInvoice
    {
        try {
            return $this->get($invoiceType, $invoiceNumber);
        } catch (SalesInvoiceNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new invoice; Twinfield gives it the next number of the invoice type.
     *
     * @throws InvalidArgumentException When the input has an invoice number: use update().
     * @throws ProcessXmlException When Twinfield refuses it, e.g. an unknown customer or article.
     */
    public function create(SalesInvoiceInput $input): SalesInvoice
    {
        if ($input->invoiceNumber !== null) {
            throw new InvalidArgumentException(sprintf(
                'Sales invoice %s %d already has a number: use update() to change it.',
                $input->invoiceType,
                $input->invoiceNumber,
            ));
        }

        return $this->save($input);
    }

    /**
     * Updates a concept invoice with the values of the input. Start from `$invoice->edit()`
     * to keep the values and lines you do not change.
     *
     * @throws InvalidArgumentException When the input has no invoice number: use create().
     * @throws SalesInvoiceNotFoundException When there is no invoice with this number.
     * @throws LogicException When the invoice is final.
     */
    public function update(SalesInvoiceInput $input): SalesInvoice
    {
        if ($input->invoiceNumber === null) {
            throw new InvalidArgumentException('The sales invoice has no number: use create() for a new invoice.');
        }

        $current = $this->get($input->invoiceType, $input->invoiceNumber);

        if ($current->isFinal()) {
            throw new LogicException(sprintf(
                'Sales invoice %s %d is final: only concept invoices can be changed.',
                $input->invoiceType,
                $input->invoiceNumber,
            ));
        }

        return $this->save($input);
    }

    /**
     * Creates the invoice, or updates it when the input has a number (one request, no check).
     * Twinfield returns the saved invoice, with its number and amounts.
     *
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function save(SalesInvoiceInput $input): SalesInvoice
    {
        $result = $this->twinfield->processXml($input->toXml($this->company())->toXml());

        return SalesInvoice::fromXml(XmlElement::root($result), $this->twinfield->organisation());
    }

    /**
     * Checks and calculates the invoice without saving it (`calculateonly="true"`): the
     * amounts, VAT and totals Twinfield would give it.
     *
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function calculate(SalesInvoiceInput $input): SalesInvoiceCalculation
    {
        $result = $this->twinfield->processXml($input->toXml($this->company(), calculateOnly: true)->toXml());

        return SalesInvoiceCalculation::fromXml(XmlElement::root($result));
    }

    /**
     * Makes a concept invoice final: it can no longer be changed, and Twinfield books the
     * sales transaction (see salesTransaction()).
     *
     * @throws SalesInvoiceNotFoundException When there is no invoice with this number.
     * @throws LogicException When the invoice is already final.
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function finalize(string $invoiceType, int $invoiceNumber): SalesInvoice
    {
        return $this->save($this->get($invoiceType, $invoiceNumber)->edit()->final());
    }

    /**
     * The sales transaction Twinfield booked for a final invoice.
     *
     * @throws LogicException When the invoice is a concept: it is not booked yet.
     */
    public function salesTransaction(SalesInvoice $invoice): SalesTransaction
    {
        $transaction = $invoice->transaction ?? throw new LogicException(sprintf(
            'Sales invoice %s %d is %s: only a final invoice has a sales transaction.',
            $invoice->invoiceType,
            $invoice->invoiceNumber,
            $invoice->status->value,
        ));

        return $this->twinfield->salesTransactions()->get($transaction->dayBook, $transaction->number);
    }

    /**
     * Search the invoice types of the company by code and name, Finder type `INV`, e.g.
     * `invoiceTypes()->all()`.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<InvoiceTypeSummary>
     */
    public function invoiceTypes(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::InvoiceTypes);

        return CodeNameSearch::forCompany($query, InvoiceTypeSummary::fromFinderRow(...))->pattern($pattern);
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Sales invoices belong to a company: use company() first.');
    }
}
