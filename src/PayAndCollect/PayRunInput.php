<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use SolveBeam\Twinfield\Data\ArrayOfArrayOfString;
use SolveBeam\Twinfield\Data\ArrayOfString;
use SolveBeam\Twinfield\PayAndCollect\Request\CreateRunRequest;
use SolveBeam\Twinfield\Period\Period;

/**
 * A new pay run: which open invoices to select, and when to pay them. Send it with
 * PayAndCollectResource::create(). Immutable: every method returns a new input.
 *
 *   PayRunInput::create('SEPANLCT', new DateTimeImmutable('2026-11-12'), '2026/11')
 *       ->method(PayRunMethod::SummariseAndDeduct)
 *       ->bank('ING')
 *       ->maxDueDate(new DateTimeImmutable('2026-11-30'));
 *
 * Everything except pay type, pay date and period is optional: Twinfield then uses the settings
 * of the pay type. A payment run selects purchase invoices, a direct debit run sales invoices.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#createrun2
 */
final class PayRunInput
{
    private ?PayRunMethod $method = null;

    private ?bool $directRemove = null;

    private ?string $bank = null;

    private ?string $dimensionFrom = null;

    private ?string $dimensionTo = null;

    private ?DateTimeImmutable $maxDueDate = null;

    private ?string $invoiceNumber = null;

    private ?string $currency = null;

    private int $discountDaysTolerance = 0;

    /** @var array<string, string> */
    private array $settings = [];

    private function __construct(
        /** Code of the pay type, e.g. `SEPANLCT` (Finder type `PAY`). */
        public readonly string $payType,
        public readonly DateTimeImmutable $payDate,
        /** Year and period the payments are posted in. */
        public readonly Period $period,
    ) {
        if ($payType === '') {
            throw new InvalidArgumentException('Pay type must not be empty.');
        }
    }

    /**
     * @param string $payType Code of the pay type, e.g. `SEPANLCT` or `SEPANLDD` (Finder type `PAY`).
     * @param Period|string $period E.g. `2026/11`.
     */
    public static function create(string $payType, DateTimeInterface $payDate, Period|string $period): self
    {
        return new self(
            $payType,
            DateTimeImmutable::createFromInterface($payDate)->setTime(0, 0),
            $period instanceof Period ? $period : Period::fromString($period),
        );
    }

    /**
     * How the invoices are summarised.
     */
    public function method(PayRunMethod $method): self
    {
        $input = clone $this;
        $input->method = $method;

        return $input;
    }

    /**
     * Whether excluded invoices are available for a new run right after posting (true), or only
     * after the run is finished (false).
     */
    public function directRemove(bool $directRemove = true): self
    {
        $input = clone $this;
        $input->directRemove = $directRemove;

        return $input;
    }

    /**
     * Code of the bank (day book) the file is for, e.g. `ING`.
     */
    public function bank(string $bank): self
    {
        $input = clone $this;
        $input->bank = self::notEmpty($bank, 'Bank');

        return $input;
    }

    /**
     * Only invoices of these suppliers or customers (level 2 dimensions), e.g. `2000` to `2999`.
     */
    public function dimensions(string $from, string $to): self
    {
        $input = clone $this;
        $input->dimensionFrom = self::notEmpty($from, 'Dimension from');
        $input->dimensionTo = self::notEmpty($to, 'Dimension to');

        return $input;
    }

    /**
     * Only invoices that are due on or before this date.
     */
    public function maxDueDate(DateTimeInterface $date): self
    {
        $input = clone $this;
        $input->maxDueDate = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return $input;
    }

    /**
     * Only the invoice with this number.
     */
    public function invoiceNumber(string $invoiceNumber): self
    {
        $input = clone $this;
        $input->invoiceNumber = self::notEmpty($invoiceNumber, 'Invoice number');

        return $input;
    }

    /**
     * Only invoices in this currency, e.g. `EUR`, for a pay type with more currencies.
     */
    public function currency(string $currency): self
    {
        $input = clone $this;
        $input->currency = self::notEmpty($currency, 'Currency');

        return $input;
    }

    /**
     * Number of days a supplier still allows a discount after it expired. Default 0.
     */
    public function discountDaysTolerance(int $days): self
    {
        if ($days < 0) {
            throw new InvalidArgumentException(sprintf('Discount days tolerance must be 0 or more, got %d.', $days));
        }

        $input = clone $this;
        $input->discountDaysTolerance = $days;

        return $input;
    }

    /**
     * SEPA scheme of a direct debit run: custom setting `SEPANLDDcollectionschema`.
     */
    public function collectionSchema(CollectionSchema $schema): self
    {
        return $this->setting('SEPANLDDcollectionschema', $schema->value);
    }

    /**
     * A custom setting of the pay type, e.g. `setting('SEPANLDDcollectionschema', '1')`. Twinfield
     * refuses a value that differs from the pay type when the pay type does not allow changing it.
     */
    public function setting(string $name, string $value): self
    {
        $input = clone $this;
        $input->settings[self::notEmpty($name, 'Setting name')] = $value;

        return $input;
    }

    /**
     * @internal
     */
    public function toRequest(string $office): CreateRunRequest
    {
        return new CreateRunRequest(
            office: $office,
            payType: $this->payType,
            payDate: self::dateTime($this->payDate),
            period: (string) $this->period,
            method: $this->method?->value,
            directRemove: $this->directRemove === null ? null : ($this->directRemove ? 'true' : 'false'),
            bankCode: $this->bank,
            dimensionFrom: $this->dimensionFrom,
            dimensionTo: $this->dimensionTo,
            maxDueDate: $this->maxDueDate === null ? null : self::dateTime($this->maxDueDate),
            invoiceNumber: $this->invoiceNumber,
            currency: $this->currency,
            discountDaysTolerance: $this->discountDaysTolerance,
            customSettings: self::settings($this->settings),
        );
    }

    /**
     * `[name, value]` pairs, as in the manual: `new string[] { "PayCostNational", "0" }`.
     *
     * @param array<string, string> $settings
     *
     * @internal
     */
    public static function settings(array $settings): ?ArrayOfArrayOfString
    {
        if ($settings === []) {
            return null;
        }

        $pairs = [];

        foreach ($settings as $name => $value) {
            $pairs[] = new ArrayOfString([(string) $name, $value]);
        }

        return new ArrayOfArrayOfString($pairs);
    }

    private static function dateTime(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d') . 'T00:00:00';
    }

    private static function notEmpty(string $value, string $label): string
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $label));
        }

        return $value;
    }
}
