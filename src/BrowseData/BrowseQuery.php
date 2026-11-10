<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use SolveBeam\Twinfield\Budget\Regime;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\Twinfield;

/**
 * Immutable query on browse data: which columns you want, and the selection criteria.
 *
 *   $result = $twinfield->company('1000')->browse(BrowseCode::GeneralLedgerDetailsV3)
 *       ->columns('fin.trs.head.code', 'fin.trs.head.number', 'fin.trs.line.dim1', 'fin.trs.line.valuesigned')
 *       ->between('fin.trs.head.yearperiod', '2026/00', '2026/55')
 *       ->equal('fin.trs.head.status', 'normal')
 *       ->get();
 *
 * Every method returns a new query, so a base query can be reused, e.g. for each period.
 *
 * - `columns()` are the fields in the result, in that order. Only ask for the fields you need:
 *   Twinfield returns all rows at once, which can be many megabytes.
 * - A criterion on a field that is not one of the columns only selects: the field is not in the
 *   result. Some fields can only be used like this, e.g. `fin.trs.head.yearperiod` in 030_3:
 *   asking for it as column gives a server error. Fields that are not visible in the browse
 *   definition (`BrowseDefinitionColumn::$visible`) may have this problem.
 * - Twinfield does not apply the default criteria of the browse definition. Without a criterion on
 *   `fin.trs.head.status` you get all transactions, also drafts.
 *
 * The data is that of the company of the client.
 *
 * @see https://developers.twinfield.com/documentation/api/request-data/browse-data/
 */
final readonly class BrowseQuery
{
    /**
     * @param list<string> $columns
     * @param array<string, BrowseCriterion> $criteria By field.
     * @param list<array{string, SortOrder}> $sort
     */
    public function __construct(
        private Twinfield $twinfield,
        private string $code,
        private array $columns = [],
        private array $criteria = [],
        private array $sort = [],
    ) {
        if ($code === '') {
            throw new InvalidArgumentException('Browse code must not be empty.');
        }
    }

    /**
     * The fields in the result, in this order, e.g. `columns('fin.trs.head.code', 'fin.trs.head.number')`.
     * Replaces the columns of the query.
     */
    public function columns(string ...$fields): self
    {
        foreach ($fields as $field) {
            self::field($field);
        }

        if (count(array_unique($fields)) !== count($fields)) {
            throw new InvalidArgumentException(sprintf(
                'Each browse column can be asked for once, got %s.',
                implode(', ', $fields),
            ));
        }

        return $this->with(columns: array_values($fields));
    }

    /**
     * Rows where the field has this value, e.g. `equal('fin.trs.head.code', 'VRK')`. Setting a
     * criterion on a field again replaces it.
     */
    public function equal(string $field, string|int|Period|Regime $value): self
    {
        return $this->criterion($field, BrowseCriterion::equal(self::value($value)));
    }

    /**
     * Rows where the field matches a pattern: `*` for any characters, `?` for one, e.g.
     * `like('fin.trs.head.code', 'VRK*')`. Not documented, but the definitions of 130 and 230 use it.
     */
    public function like(string $field, string $pattern): self
    {
        return $this->criterion($field, BrowseCriterion::like($pattern));
    }

    /**
     * Rows where the field is from … to, both included, e.g.
     * `between('fin.trs.head.yearperiod', '2026/00', '2026/55')` for a whole year.
     */
    public function between(
        string $field,
        string|int|Period|Regime $from,
        string|int|Period|Regime $to,
    ): self {
        return $this->criterion($field, BrowseCriterion::between(self::value($from), self::value($to)));
    }

    /**
     * Rows where the field is not from … to. Not documented by Twinfield, but it works.
     */
    public function notBetween(
        string $field,
        string|int|Period|Regime $from,
        string|int|Period|Regime $to,
    ): self {
        return $this->criterion($field, BrowseCriterion::notBetween(self::value($from), self::value($to)));
    }

    /**
     * Rows where a date field (`yyyymmdd`) is between two days, both included, e.g.
     * `fin.trs.head.date`.
     */
    public function betweenDates(string $field, DateTimeInterface $from, DateTimeInterface $to): self
    {
        return $this->between($field, XmlValue::date($from), XmlValue::date($to));
    }

    /**
     * Rows where a date and time field (`yyyymmddhhmmss`) is between two moments, both included,
     * e.g. `fin.trs.head.modified` for the transactions changed since the last download. Twinfield
     * uses UTC: the moments are converted to UTC.
     */
    public function betweenDateTimes(string $field, DateTimeInterface $from, DateTimeInterface $to): self
    {
        return $this->between($field, self::utc($from), self::utc($to));
    }

    /**
     * Sorts the result on a field; at most 3 fields, the first one first.
     *
     * Not every browse code sorts: in our tests 000 did, 030_3 ignored the sort fields.
     */
    public function sortBy(string $field, SortOrder $order = SortOrder::Ascending): self
    {
        self::field($field);

        $sort = array_values(array_filter($this->sort, static fn (array $item): bool => $item[0] !== $field));
        $sort[] = [$field, $order];

        if (count($sort) > 3) {
            throw new InvalidArgumentException('Twinfield sorts browse data on at most 3 fields.');
        }

        return $this->with(sort: $sort);
    }

    /**
     * The browse code, e.g. `030_3`.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * The ProcessXml request, e.g. to log it or to try it in Postman.
     *
     * @internal
     */
    public function toXml(): string
    {
        $xml = XmlBuilder::create('columns', ['code' => $this->code, 'optimize' => 'true']);

        if ($this->sort !== []) {
            $sort = $xml->add($xml->root(), 'sort');

            foreach ($this->sort as [$field, $order]) {
                $xml->add($sort, 'field', $field, ['order' => $order->value]);
            }
        }

        foreach ($this->columns as $field) {
            $this->addColumn($xml, $field, true);
        }

        foreach (array_keys($this->criteria) as $field) {
            if (!in_array($field, $this->columns, true)) {
                $this->addColumn($xml, $field, false);
            }
        }

        return $xml->toXml();
    }

    /**
     * Sends the query and returns all rows.
     *
     * @throws \LogicException When the client has no company.
     * @throws \SolveBeam\Twinfield\ProcessXml\ProcessXmlException When Twinfield refuses the query,
     *                                                           e.g. for an unknown field.
     */
    public function get(): BrowseResult
    {
        if ($this->columns === []) {
            throw new InvalidArgumentException('Ask for at least one column, e.g. columns(\'fin.trs.head.code\').');
        }

        $company = $this->twinfield->companyReference();
        $result = $this->twinfield->processXml($this->toXml());

        return BrowseResult::fromXml(XmlElement::root($result), $company, $this->columns);
    }

    private function criterion(string $field, BrowseCriterion $criterion): self
    {
        self::field($field);

        // Twinfield gives the data of another company with this criterion, not that of the client.
        $company = $this->twinfield->companyCode();

        if (
            $field === 'fin.trs.head.office'
            && ($criterion->operator !== BrowseOperator::Equal || $criterion->from !== $company)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Browse data is that of the company of the client (%s): use company() for another company '
                . 'instead of a criterion on fin.trs.head.office.',
                $company ?? 'none',
            ));
        }

        return $this->with(criteria: [...$this->criteria, $field => $criterion]);
    }

    private function addColumn(XmlBuilder $xml, string $field, bool $visible): void
    {
        $column = $xml->add($xml->root(), 'column');
        $xml->add($column, 'field', $field);

        // Without <visible> the column only selects, it is not in the result.
        if ($visible) {
            $xml->add($column, 'visible', 'true');
        }

        $criterion = $this->criteria[$field] ?? null;

        if ($criterion === null) {
            return;
        }

        $xml->add($column, 'operator', $criterion->operator->value);
        $xml->add($column, 'from', $criterion->from);

        if ($criterion->to !== null) {
            $xml->add($column, 'to', $criterion->to);
        }
    }

    private static function field(string $field): void
    {
        if (preg_match('~^[a-z0-9_]+(\.[a-z0-9_]+)+$~i', $field) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a browse field, e.g. fin.trs.head.code.',
                $field,
            ));
        }
    }

    private static function value(string|int|Period|Regime $value): string
    {
        return match (true) {
            $value instanceof Regime => $value->value,
            default => (string) $value,
        };
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('YmdHis');
    }

    /**
     * @param list<string>|null $columns
     * @param array<string, BrowseCriterion>|null $criteria
     * @param list<array{string, SortOrder}>|null $sort
     */
    private function with(?array $columns = null, ?array $criteria = null, ?array $sort = null): self
    {
        return new self(
            $this->twinfield,
            $this->code,
            $columns ?? $this->columns,
            $criteria ?? $this->criteria,
            $sort ?? $this->sort,
        );
    }
}
