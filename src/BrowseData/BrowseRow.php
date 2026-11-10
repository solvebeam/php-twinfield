<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Transaction\TransactionLineReference;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A row of a browse result. Read a value by its field, with the reader for its type:
 *
 *   $row->string('fin.trs.head.code');            // VRK
 *   $row->int('fin.trs.head.number');             // 202600003
 *   $row->amount('fin.trs.line.valuesigned');     // Amount -121.00
 *   $row->date('fin.trs.head.date');              // DateTimeImmutable
 *   $row->period('fin.trs.head.yearperiod');      // Period 2026/03
 *   $row->displayName('fin.trs.head.status');     // Final (in the language of the user)
 *
 * A reader throws an InvalidArgumentException for a field that is not a column of the result, and
 * an UnexpectedResponseException for a value that does not fit, e.g. an empty amount.
 */
final readonly class BrowseRow
{
    /**
     * @param array<string, array{type: BrowseValueType, value: string, name: ?string}> $cells By field.
     */
    public function __construct(
        /** The company of the row: always the company of the client. */
        public CompanyReference $company,
        /**
         * The transaction line of the row (`<key>`), e.g. for a shadow database. Null for browse
         * codes with totals instead of transaction lines, e.g. 040_1, where `<key>` only has the
         * company.
         */
        public ?TransactionLineReference $transactionLine,
        private array $cells,
    ) {
    }

    /**
     * @param list<BrowseResultColumn> $columns The columns of `<th>`, in order.
     */
    public static function fromXml(XmlElement $tr, array $columns, CompanyReference $company): self
    {
        $tds = [];
        $key = null;

        foreach ($tr->element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            match ($child->localName) {
                'td' => $tds[] = $child,
                'key' => $key = new XmlElement($child, $tr->path . '/key'),
                default => throw new UnexpectedResponseException(sprintf(
                    '%s contains <%s>, expected <td> and <key>.',
                    $tr->path,
                    $child->localName,
                )),
            };
        }

        if (count($tds) !== count($columns)) {
            throw new UnexpectedResponseException(sprintf(
                '%s has %d cells, expected %d.',
                $tr->path,
                count($tds),
                count($columns),
            ));
        }

        if ($key === null) {
            throw new UnexpectedResponseException(sprintf('%s/key is missing.', $tr->path));
        }

        $cells = [];

        foreach ($columns as $index => $column) {
            $td = $tds[$index];

            $cells[$column->field] = [
                'type' => $column->type,
                'value' => $td->textContent,
                'name' => $td->hasAttribute('name') ? $td->getAttribute('name') : null,
            ];
        }

        return new self($company, self::transactionLine($key, $company), $cells);
    }

    /**
     * True when the field is a column of the result.
     */
    public function has(string $field): bool
    {
        return isset($this->cells[$field]);
    }

    /**
     * All values as Twinfield returned them, by field.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return array_map(static fn (array $cell): string => $cell['value'], $this->cells);
    }

    /**
     * A value that must not be empty, e.g. the day book `fin.trs.head.code`.
     */
    public function string(string $field): string
    {
        return $this->nullableString($field)
            ?? throw new UnexpectedResponseException(sprintf('%s is empty.', $field));
    }

    /**
     * A value that may be empty (null), e.g. the description `fin.trs.line.description`.
     */
    public function nullableString(string $field): ?string
    {
        $value = $this->cell($field)['value'];

        return $value === '' ? null : $value;
    }

    /**
     * A whole number, e.g. the transaction number `fin.trs.head.number` (`-1` for a draft) or the
     * year `fin.trs.head.year`.
     */
    public function int(string $field): int
    {
        $value = $this->typed($field, BrowseValueType::Number, BrowseValueType::Decimal)['value'];

        if (preg_match('~^-?\d+$~', $value) !== 1) {
            throw new UnexpectedResponseException(sprintf('%s is "%s", expected a whole number.', $field, $value));
        }

        return (int) $value;
    }

    /**
     * A number that may have decimals and may be empty (null), e.g. the quantity
     * `fin.trs.line.quantity`.
     *
     * @return numeric-string|null
     */
    public function nullableDecimal(string $field): ?string
    {
        $value = $this->typed($field, BrowseValueType::Number, BrowseValueType::Decimal)['value'];

        if ($value === '') {
            return null;
        }

        if (preg_match('~^-?\d+(?:\.\d+)?$~', $value) !== 1 || !is_numeric($value)) {
            throw new UnexpectedResponseException(sprintf('%s is "%s", expected a number.', $field, $value));
        }

        return $value;
    }

    /**
     * An amount, e.g. `fin.trs.line.valuesigned` (negative for credit).
     */
    public function amount(string $field): Amount
    {
        $value = $this->typed($field, BrowseValueType::Value)['value'];

        if (preg_match('~^-?\d+(?:\.\d{1,2})?$~', $value) !== 1) {
            throw new UnexpectedResponseException(sprintf('%s is "%s", expected an amount.', $field, $value));
        }

        return Amount::fromString($value);
    }

    /**
     * A date (`yyyymmdd`), e.g. the transaction date `fin.trs.head.date`.
     */
    public function date(string $field): DateTimeImmutable
    {
        return $this->nullableDate($field)
            ?? throw new UnexpectedResponseException(sprintf('%s is empty.', $field));
    }

    /**
     * A date that may be empty (null), e.g. the match date `fin.trs.line.matchdate`.
     */
    public function nullableDate(string $field): ?DateTimeImmutable
    {
        $value = $this->typed($field, BrowseValueType::Date)['value'];

        if ($value === '') {
            return null;
        }

        try {
            return XmlValue::toDate($value);
        } catch (InvalidArgumentException) {
            throw new UnexpectedResponseException(sprintf('%s is "%s", expected a date yyyymmdd.', $field, $value));
        }
    }

    /**
     * A date and time (`yyyymmddhhmmss`) in UTC, e.g. `fin.trs.head.modified`.
     */
    public function dateTime(string $field): DateTimeImmutable
    {
        $value = $this->typed($field, BrowseValueType::DateTime)['value'];
        $utc = new DateTimeZone('UTC');
        $dateTime = preg_match('~^\d{14}$~', $value) === 1
            ? DateTimeImmutable::createFromFormat('!YmdHis', $value, $utc)
            : false;

        if ($dateTime === false || $dateTime->format('YmdHis') !== $value) {
            throw new UnexpectedResponseException(sprintf(
                '%s is "%s", expected a date and time yyyymmddhhmmss.',
                $field,
                $value,
            ));
        }

        return $dateTime;
    }

    /**
     * A year and period (`yyyy/pp`), e.g. `fin.trs.head.yearperiod`.
     */
    public function period(string $field): Period
    {
        $value = $this->cell($field)['value'];

        try {
            return Period::fromString($value);
        } catch (InvalidArgumentException) {
            throw new UnexpectedResponseException(sprintf('%s is "%s", expected a period yyyy/pp.', $field, $value));
        }
    }

    /**
     * The value as Twinfield shows it, in the language of the user, when it differs from the value:
     * e.g. `Final` for status `final`, `Debet` for `debit` and `25/02/2026` for date `20260225`.
     * Null when Twinfield gives none.
     */
    public function displayName(string $field): ?string
    {
        return $this->cell($field)['name'];
    }

    /**
     * @return array{type: BrowseValueType, value: string, name: ?string}
     */
    private function cell(string $field): array
    {
        return $this->cells[$field] ?? throw new InvalidArgumentException(sprintf(
            '%s is not a column of the browse result; the columns are %s.',
            $field,
            implode(', ', array_keys($this->cells)),
        ));
    }

    /**
     * @return array{type: BrowseValueType, value: string, name: ?string}
     */
    private function typed(string $field, BrowseValueType ...$types): array
    {
        $cell = $this->cell($field);

        if (!in_array($cell['type'], $types, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s is a %s column, not %s.',
                $field,
                $cell['type']->value,
                implode(' or ', array_map(static fn (BrowseValueType $type): string => $type->value, $types)),
            ));
        }

        return $cell;
    }

    /**
     * `<key>` has the office, and for transaction lines also the day book, number and line.
     */
    private static function transactionLine(XmlElement $key, CompanyReference $company): ?TransactionLineReference
    {
        $office = $key->string('office');

        if ($office !== $company->code) {
            throw new UnexpectedResponseException(sprintf(
                '%s/office is %s, expected the company of the client, %s.',
                $key->path,
                $office,
                $company->code,
            ));
        }

        $parts = array_filter(
            ['code', 'number', 'line'],
            static fn (string $name): bool => $key->nullableChild($name) !== null,
        );

        if ($parts === []) {
            return null;
        }

        if (count($parts) !== 3) {
            throw new UnexpectedResponseException(sprintf(
                '%s has %s, expected code, number and line, or none of them.',
                $key->path,
                implode(', ', $parts),
            ));
        }

        try {
            return new TransactionLineReference(
                $company,
                $key->string('code'),
                $key->int('number'),
                $key->int('line'),
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                sprintf('%s: %s', $key->path, $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}
