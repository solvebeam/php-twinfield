<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A browse field (`<list><type>browsefields</type></list>`): its data type and, for fields with a
 * fixed list of values, the values to use in a criterion.
 */
final readonly class BrowseField
{
    /**
     * @param list<BrowseFieldOption> $options
     */
    public function __construct(
        /** E.g. `fin.trs.head.status`. */
        public string $code,
        public BrowseFieldDataType $dataType,
        /** The Finder type Twinfield uses to look up values, e.g. `TRS` for day books; null for none. */
        public ?string $finder,
        /** The values of a field with a fixed list (`special`); empty for other fields. */
        public array $options,
    ) {
    }

    public static function fromXml(XmlElement $field): self
    {
        $options = array_map(
            static fn (XmlElement $option): BrowseFieldOption => new BrowseFieldOption(
                $option->text(),
                $option->attribute('name') === '' ? null : $option->attribute('name'),
            ),
            $field->child('dropdown')->list('option'),
        );

        return new self(
            code: $field->string('code'),
            dataType: $field->enum('datatype', BrowseFieldDataType::class),
            finder: $field->nullableString('finder'),
            options: $options,
        );
    }
}
