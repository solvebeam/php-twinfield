<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use ArrayIterator;
use Countable;
use DOMElement;
use IteratorAggregate;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;
use Traversable;

/**
 * Result of a browse query: the columns and all rows.
 *
 *   foreach ($result as $row) {
 *       echo $row->string('fin.trs.head.code'), ' ', $row->amount('fin.trs.line.valuesigned'), PHP_EOL;
 *   }
 *
 * Twinfield returns all rows at once; there is no paging.
 *
 * @implements IteratorAggregate<int, BrowseRow>
 */
final readonly class BrowseResult implements Countable, IteratorAggregate
{
    /**
     * @param list<BrowseResultColumn> $columns
     * @param list<BrowseRow> $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
    ) {
    }

    /**
     * From the `<browse>` response of an optimized request (`optimize="true"`): the cells of the
     * rows have no `field` and `type` attributes, so they are read in the order of `<th>`.
     *
     * @param list<string> $columns The columns asked for, to check the response.
     *
     * @throws UnexpectedResponseException When the response is not what was asked for.
     */
    public static function fromXml(XmlElement $browse, CompanyReference $company, array $columns): self
    {
        if ($browse->element->localName !== 'browse') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <browse> response, got <%s>.',
                $browse->element->localName,
            ));
        }

        $resultColumns = array_map(
            static fn (XmlElement $td): BrowseResultColumn => new BrowseResultColumn(
                $td->text(),
                $td->enumAttribute('type', BrowseValueType::class),
            ),
            self::elements($browse->child('th'), 'td'),
        );

        $fields = array_map(static fn (BrowseResultColumn $column): string => $column->field, $resultColumns);

        if ($fields !== $columns) {
            throw new UnexpectedResponseException(sprintf(
                'Asked for the columns %s, got %s.',
                implode(', ', $columns),
                $fields === [] ? 'none' : implode(', ', $fields),
            ));
        }

        $rows = array_map(
            static fn (XmlElement $tr): BrowseRow => BrowseRow::fromXml($tr, $resultColumns, $company),
            self::elements($browse, 'tr'),
        );

        self::checkComplete($browse, count($rows));

        return new self($resultColumns, $rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @return Traversable<int, BrowseRow>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    /**
     * Twinfield returns all rows (`first` 1, `last` = `total`; 0, 0, 0 when there are none). Check
     * it anyway: a shadow database must never miss rows without notice.
     */
    private static function checkComplete(XmlElement $browse, int $count): void
    {
        $first = $browse->intAttribute('first');
        $last = $browse->intAttribute('last');
        $total = $browse->intAttribute('total');

        $expected = $total === 0 ? [0, 0] : [1, $total];

        if ([$first, $last] !== $expected || $count !== $total) {
            throw new UnexpectedResponseException(sprintf(
                'Twinfield returned rows %d to %d of %d (%d rows), expected all rows.',
                $first,
                $last,
                $total,
                $count,
            ));
        }
    }

    /**
     * Child elements with this name; `<th>` and `<browse>` have no other children we need.
     *
     * @return list<XmlElement>
     */
    private static function elements(XmlElement $parent, string $name): array
    {
        $elements = [];
        $index = 0;

        foreach ($parent->element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $elements[] = new XmlElement($child, sprintf('%s/%s[%d]', $parent->path, $name, ++$index));
            }
        }

        return $elements;
    }
}
