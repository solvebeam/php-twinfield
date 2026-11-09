<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SalesInvoice;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\VatCode\VatCode;

/**
 * A sales invoice to create, update or calculate. Immutable: every method returns a new input.
 *
 *   $input = SalesInvoiceInput::create('FACTUUR', customer: '1000')
 *       ->dueDate(new DateTimeImmutable('2026-12-09'))
 *       ->paymentMethod(PaymentMethod::Bank)
 *       ->article('510', quantity: '2')
 *       ->oneOff('Consultancy November', '950.00', vatCode: 'VH');
 *
 * Twinfield gives the invoice number and calculates the amounts and VAT. The invoice is saved
 * as concept; call final() to make it final at once, which books the sales transaction.
 *
 * For an existing concept invoice, start from `$invoice->edit()`.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/sales-invoices/
 */
final readonly class SalesInvoiceInput
{
    /**
     * @param list<SalesInvoiceLineInput> $lines
     */
    private function __construct(
        public string $invoiceType,
        public string $customer,
        /** Number of the invoice to update; null for a new invoice. */
        public ?int $invoiceNumber,
        public SalesInvoiceStatus $status,
        public ?DateTimeImmutable $invoiceDate,
        public ?DateTimeImmutable $dueDate,
        public ?DateTimeImmutable $performanceDate,
        public ?string $bank,
        public ?FinancialPeriod $period,
        /** False to suppress warnings about a date outside the period. */
        public bool $periodRaiseWarning,
        public ?string $currency,
        public ?PaymentMethod $paymentMethod,
        public ?int $invoiceAddressNumber,
        public ?int $deliverAddressNumber,
        public ?string $headerText,
        public ?string $footerText,
        public array $lines,
        /** False to save without warnings (`raisewarning="false"`). */
        public bool $raiseWarning,
    ) {
        XmlValue::code($invoiceType);
        XmlValue::code($customer);

        if ($invoiceNumber !== null && $invoiceNumber < 1) {
            throw new InvalidArgumentException(
                sprintf('Sales invoice number must be 1 or more, %d given.', $invoiceNumber),
            );
        }

        if ($status === SalesInvoiceStatus::Default) {
            throw new InvalidArgumentException(
                'Twinfield only returns status default: save an invoice as concept or final.',
            );
        }

        foreach ([$bank, $currency] as $code) {
            if ($code !== null) {
                XmlValue::code($code);
            }
        }

        $addresses = ['invoice address' => $invoiceAddressNumber, 'delivery address' => $deliverAddressNumber];

        foreach ($addresses as $field => $number) {
            if ($number !== null && $number < 1) {
                throw new InvalidArgumentException(
                    sprintf('Sales invoice %s number must be 1 or more, %d given.', $field, $number),
                );
            }
        }
    }

    /**
     * A new sales invoice, saved as concept.
     *
     * @param string $invoiceType Invoice type code, e.g. `FACTUUR`; see
     *                            `salesInvoices()->invoiceTypes()`.
     * @param string $customer Customer code, e.g. `1000`.
     */
    public static function create(string $invoiceType, string $customer): self
    {
        return new self(
            invoiceType: $invoiceType,
            customer: $customer,
            invoiceNumber: null,
            status: SalesInvoiceStatus::Concept,
            invoiceDate: null,
            dueDate: null,
            performanceDate: null,
            bank: null,
            period: null,
            periodRaiseWarning: true,
            currency: null,
            paymentMethod: null,
            invoiceAddressNumber: null,
            deliverAddressNumber: null,
            headerText: null,
            footerText: null,
            lines: [],
            raiseWarning: true,
        );
    }

    /**
     * An existing concept invoice, to update. Only the values you set are sent; start from
     * `$invoice->edit()` to keep all current values and lines.
     */
    public static function existing(string $invoiceType, int $invoiceNumber, string $customer): self
    {
        return self::create($invoiceType, $customer)->with(['invoiceNumber' => $invoiceNumber]);
    }

    public function customer(string $customer): self
    {
        return $this->with(['customer' => $customer]);
    }

    /**
     * Without it, Twinfield uses the date of today.
     */
    public function invoiceDate(?DateTimeInterface $date): self
    {
        return $this->with(['invoiceDate' => self::day($date)]);
    }

    /**
     * Without it, Twinfield takes the due date from the payment terms.
     */
    public function dueDate(?DateTimeInterface $date): self
    {
        return $this->with(['dueDate' => self::day($date)]);
    }

    /**
     * Only when the performance date is set up on the invoice header.
     */
    public function performanceDate(?DateTimeInterface $date): self
    {
        return $this->with(['performanceDate' => self::day($date)]);
    }

    /**
     * Bank code, e.g. `BNK`.
     */
    public function bank(?string $bank): self
    {
        return $this->with(['bank' => $bank === '' ? null : $bank]);
    }

    /**
     * Financial period, e.g. `'2026/11'` or `FinancialPeriod::of(2026, 11)`.
     *
     * @param bool $raiseWarning False to suppress the warning that the invoice date is outside
     *                           the period.
     */
    public function period(FinancialPeriod|string|null $period, bool $raiseWarning = true): self
    {
        return $this->with([
            'period' => is_string($period) ? FinancialPeriod::fromString($period) : $period,
            'periodRaiseWarning' => $raiseWarning,
        ]);
    }

    /**
     * Currency code, e.g. `EUR`.
     */
    public function currency(?string $currency): self
    {
        return $this->with(['currency' => $currency === '' ? null : $currency]);
    }

    public function paymentMethod(?PaymentMethod $paymentMethod): self
    {
        return $this->with(['paymentMethod' => $paymentMethod]);
    }

    /**
     * Number of the invoice address of the customer; null for the default address.
     */
    public function invoiceAddress(?int $number): self
    {
        return $this->with(['invoiceAddressNumber' => $number]);
    }

    /**
     * Number of the delivery address of the customer; null for the default address.
     */
    public function deliveryAddress(?int $number): self
    {
        return $this->with(['deliverAddressNumber' => $number]);
    }

    /**
     * Text at the top of the invoice.
     */
    public function headerText(?string $text): self
    {
        return $this->with(['headerText' => $text === '' ? null : $text]);
    }

    /**
     * Text at the bottom of the invoice.
     */
    public function footerText(?string $text): self
    {
        return $this->with(['footerText' => $text === '' ? null : $text]);
    }

    /**
     * Adds a line.
     */
    public function line(SalesInvoiceLineInput $line): self
    {
        return $this->with(['lines' => [...$this->lines, $line]]);
    }

    /**
     * Adds a line with an article. Twinfield takes the price, VAT code and account from the article.
     *
     * @param numeric-string|int $quantity
     */
    public function article(string $article, string|int $quantity = '1', ?string $subarticle = null): self
    {
        return $this->line(SalesInvoiceLineInput::article($article, $subarticle)->quantity($quantity));
    }

    /**
     * Adds a line with the one-off item `0`: your own description and price per unit without
     * VAT, for an invoice type with prices excluding VAT.
     *
     * @param Amount|numeric-string $unitPriceExcl
     * @param numeric-string|int $quantity
     * @param ?string $account General ledger account, only when the invoice type allows it.
     */
    public function oneOff(
        string $description,
        Amount|string $unitPriceExcl,
        string|VatCode|null $vatCode = null,
        string|int $quantity = '1',
        ?string $account = null,
    ): self {
        return $this->line(
            SalesInvoiceLineInput::oneOff($description)
                ->unitPriceExcl($unitPriceExcl)
                ->vatCode($vatCode)
                ->quantity($quantity)
                ->account($account),
        );
    }

    /**
     * Removes the line with this id, e.g. from `$invoice->edit()`.
     *
     * @throws InvalidArgumentException When the input has no line with this id.
     */
    public function removeLine(int $id): self
    {
        $lines = array_values(array_filter(
            $this->lines,
            static fn (SalesInvoiceLineInput $line): bool => $line->id !== $id,
        ));

        if (count($lines) === count($this->lines)) {
            throw new InvalidArgumentException(sprintf('The sales invoice has no line with id %d.', $id));
        }

        return $this->with(['lines' => $lines]);
    }

    /**
     * Save as concept (default): the invoice can still be changed.
     */
    public function concept(): self
    {
        return $this->with(['status' => SalesInvoiceStatus::Concept]);
    }

    /**
     * Save as final: no changes anymore, and Twinfield books the sales transaction.
     */
    public function final(): self
    {
        return $this->with(['status' => SalesInvoiceStatus::Final]);
    }

    /**
     * Save without warnings (`raisewarning="false"`).
     */
    public function withoutWarnings(): self
    {
        return $this->with(['raiseWarning' => false]);
    }

    /**
     * The `<salesinvoice>` request.
     *
     * @param bool $calculateOnly True to only check and calculate it (`calculateonly="true"`).
     *
     * @throws LogicException When the input has no lines: an invoice needs at least one.
     */
    public function toXml(string $company, bool $calculateOnly = false): XmlBuilder
    {
        if ($this->lines === []) {
            throw new LogicException('A sales invoice needs at least one line.');
        }

        $attributes = [];

        if ($calculateOnly) {
            $attributes['calculateonly'] = 'true';
        }

        if (!$this->raiseWarning) {
            $attributes['raisewarning'] = 'false';
        }

        $xml = XmlBuilder::create('salesinvoice', $attributes);
        $header = $xml->add($xml->root(), 'header');

        $xml->add($header, 'office', $company);
        $xml->add($header, 'invoicetype', $this->invoiceType);

        $dates = [
            'invoicedate' => $this->invoiceDate,
            'duedate' => $this->dueDate,
            'performancedate' => $this->performanceDate,
        ];

        foreach ($dates as $name => $date) {
            if ($date !== null) {
                $xml->add($header, $name, XmlValue::date($date));
            }
        }

        if ($this->bank !== null) {
            $xml->add($header, 'bank', $this->bank);
        }

        $xml->add($header, 'customer', $this->customer);

        if ($this->period !== null) {
            $periodAttributes = $this->periodRaiseWarning ? [] : ['raisewarning' => 'false'];
            $xml->add($header, 'period', (string) $this->period, $periodAttributes);
        }

        if ($this->currency !== null) {
            $xml->add($header, 'currency', $this->currency);
        }

        if ($this->invoiceNumber !== null) {
            $xml->add($header, 'invoicenumber', (string) $this->invoiceNumber);
        }

        $xml->add($header, 'status', $this->status->value);

        if ($this->paymentMethod !== null) {
            $xml->add($header, 'paymentmethod', $this->paymentMethod->value);
        }

        $optional = [
            'invoiceaddressnumber' => self::intOrNull($this->invoiceAddressNumber),
            'deliveraddressnumber' => self::intOrNull($this->deliverAddressNumber),
            'headertext' => $this->headerText,
            'footertext' => $this->footerText,
        ];

        foreach ($optional as $name => $value) {
            if ($value !== null) {
                $xml->add($header, $name, $value);
            }
        }

        $lines = $xml->add($xml->root(), 'lines');

        // Existing lines keep their id; new lines get the next free ids.
        $ids = array_map(static fn (SalesInvoiceLineInput $line): int => $line->id ?? 0, $this->lines);
        $nextId = max([0, ...$ids]) + 1;

        foreach ($this->lines as $line) {
            $line->appendTo($xml, $lines, $line->id ?? $nextId++);
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }

    private static function intOrNull(?int $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function day(?DateTimeInterface $date): ?DateTimeImmutable
    {
        return $date === null ? null : DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
