<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A sales invoice of the Classic invoicing module, as Twinfield returns it.
 *
 *   $invoice = $twinfield->salesInvoices()->get('FACTUUR', 5);
 *
 *   echo $invoice->invoiceType, ' ', $invoice->invoiceNumber, ' ', $invoice->customer, ': ',
 *       $invoice->totals->valueInc, ' ', $invoice->currency, PHP_EOL;
 *
 *   foreach ($invoice->lines as $line) {
 *       echo $line->quantity, ' x ', $line->description, ' ', $line->valueExcl, PHP_EOL;
 *   }
 *
 * A final invoice has the sales transaction Twinfield booked in `$transaction`. Only a
 * concept invoice can be changed: call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/
 */
final readonly class SalesInvoice
{
    /**
     * @param non-empty-list<SalesInvoiceLine> $lines
     * @param list<SalesInvoiceVatLine> $vatLines
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Invoice type code, e.g. `FACTUUR`. */
        public string $invoiceType,
        /** Invoice number, given by Twinfield from the invoice type. */
        public int $invoiceNumber,
        public DateTimeImmutable $invoiceDate,
        public DateTimeImmutable $dueDate,
        /** Null when the performance date is not set up on the invoice header. */
        public ?DateTimeImmutable $performanceDate,
        /** Bank code, e.g. `BNK`. */
        public string $bank,
        /** Customer code, e.g. `1000`. */
        public string $customer,
        public Period $period,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        public SalesInvoiceStatus $status,
        public PaymentMethod $paymentMethod,
        /** Number of the invoice address of the customer. */
        public int $invoiceAddressNumber,
        /** Number of the delivery address of the customer. */
        public int $deliverAddressNumber,
        /** Null when empty. */
        public ?string $headerText,
        /** Null when empty. */
        public ?string $footerText,
        public array $lines,
        public SalesInvoiceTotals $totals,
        public array $vatLines,
        /** The booked sales transaction; only for a final invoice. */
        public ?SalesInvoiceTransaction $transaction,
    ) {
        $required = [
            'invoiceType' => $invoiceType,
            'bank' => $bank,
            'customer' => $customer,
            'currency' => $currency,
        ];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Sales invoice %s must not be empty.', $field));
            }
        }

        if ($invoiceNumber < 1) {
            throw new InvalidArgumentException(
                sprintf('Sales invoice number must be 1 or more, %d given.', $invoiceNumber),
            );
        }

        if (($status === SalesInvoiceStatus::Final) !== ($transaction !== null)) {
            throw new InvalidArgumentException(sprintf(
                'Sales invoice %s %d is %s %s a sales transaction: only a final invoice has one.',
                $invoiceType,
                $invoiceNumber,
                $status->value,
                $transaction === null ? 'without' : 'with',
            ));
        }
    }

    /**
     * From a `<salesinvoice>` element of a read or save response.
     *
     * @param Organisation $organisation Organisation of the access token: Twinfield leaves it out
     *                                   of the response.
     */
    public static function fromXml(XmlElement $invoice, Organisation $organisation): self
    {
        $header = $invoice->child('header');
        $lines = array_map(SalesInvoiceLine::fromXml(...), $invoice->child('lines')->list('line'));

        if ($lines === []) {
            throw new UnexpectedResponseException(sprintf('%s/lines has no <line>.', $invoice->path));
        }

        // Only a final invoice has <financials>.
        $financials = $invoice->nullableChild('financials');

        try {
            return new self(
                company: new CompanyReference($organisation, $header->string('office')),
                invoiceType: $header->string('invoicetype'),
                invoiceNumber: $header->int('invoicenumber'),
                invoiceDate: TransactionXml::date($header, 'invoicedate'),
                dueDate: TransactionXml::date($header, 'duedate'),
                // Left out when not set up on the invoice header.
                performanceDate: TransactionXml::optionalDate($header, 'performancedate'),
                bank: $header->string('bank'),
                customer: $header->string('customer'),
                period: TransactionXml::period($header, 'period'),
                currency: $header->string('currency'),
                status: $header->enum('status', SalesInvoiceStatus::class),
                paymentMethod: $header->enum('paymentmethod', PaymentMethod::class),
                invoiceAddressNumber: $header->int('invoiceaddressnumber'),
                deliverAddressNumber: $header->int('deliveraddressnumber'),
                // Empty when no text is entered.
                headerText: $header->nullableString('headertext'),
                footerText: $header->nullableString('footertext'),
                lines: $lines,
                totals: SalesInvoiceTotals::fromXml($invoice->child('totals')),
                vatLines: array_map(SalesInvoiceVatLine::fromXml(...), $invoice->child('vatlines')->list('vatline')),
                transaction: $financials === null ? null : SalesInvoiceTransaction::fromXml($financials),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $invoice->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function isFinal(): bool
    {
        return $this->status === SalesInvoiceStatus::Final;
    }

    /**
     * Input with all values and lines of this concept invoice, to change and pass to update().
     * The lines keep their id, so the update changes them instead of adding them again.
     *
     *   $invoices->update($invoice->edit()->footerText('Thank you for your order.'));
     *
     * @throws LogicException When the invoice is final: Twinfield can't change it anymore.
     */
    public function edit(): SalesInvoiceInput
    {
        if ($this->isFinal()) {
            throw new LogicException(sprintf(
                'Sales invoice %s %d is final: only concept invoices can be changed.',
                $this->invoiceType,
                $this->invoiceNumber,
            ));
        }

        $input = SalesInvoiceInput::existing($this->invoiceType, $this->invoiceNumber, $this->customer)
            ->invoiceDate($this->invoiceDate)
            ->dueDate($this->dueDate)
            ->performanceDate($this->performanceDate)
            ->bank($this->bank)
            ->period($this->period)
            ->currency($this->currency)
            ->paymentMethod($this->paymentMethod)
            ->invoiceAddress($this->invoiceAddressNumber)
            ->deliveryAddress($this->deliverAddressNumber)
            ->headerText($this->headerText)
            ->footerText($this->footerText);

        foreach ($this->lines as $line) {
            $input = $input->line($line->edit());
        }

        return $input;
    }
}
