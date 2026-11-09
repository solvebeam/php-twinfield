<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A column of a browse definition: a field, and whether it can be a selection criterion.
 */
final readonly class BrowseDefinitionColumn
{
    public function __construct(
        /** Position in the definition, from 1. */
        public int $id,
        /** E.g. `fin.trs.head.yearperiod`. */
        public string $field,
        /** Column header in the language of the user, e.g. `Periode`. Can be empty. */
        public string $label,
        /**
         * Whether Twinfield shows the field as column. When false, use it as criterion only:
         * asking for it as column can give a server error (e.g. `fin.trs.head.yearperiod` in
         * 030_3, while `fin.trs.head.modified` works).
         */
        public bool $visible,
        /** Whether the field can be a selection criterion. */
        public bool $ask,
        /** The operator Twinfield proposes for the criterion; `None` when `ask` is false. */
        public BrowseOperator $operator,
        /**
         * The proposed `from` value, e.g. `normal` for the status, or `$DEFAULT$` for a value
         * Twinfield fills in (e.g. the current period). Twinfield does not apply it to a query.
         */
        public ?string $from,
        /** The proposed `to` value. */
        public ?string $to,
    ) {
    }

    public static function fromXml(XmlElement $column): self
    {
        return new self(
            id: $column->intAttribute('id'),
            field: $column->string('field'),
            label: $column->child('label')->text(),
            visible: $column->bool('visible'),
            ask: $column->bool('ask'),
            operator: $column->enum('operator', BrowseOperator::class),
            from: $column->nullableString('from'),
            to: $column->nullableString('to'),
        );
    }
}
