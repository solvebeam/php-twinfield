<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\BrowseData;

use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A browse definition: the fields of a browse code, and which of them can be a criterion.
 *
 *   $definition = $twinfield->browseDefinitions()->get('030_3');
 *
 *   foreach ($definition->criteria() as $column) {
 *       echo $column->field, ' ', $column->operator->value, PHP_EOL;
 *   }
 *
 * Read it once to design a query; the definitions are also in `browse-definitions/` in the
 * repository. Labels and names are in the language of the user.
 *
 * @see https://developers.twinfield.com/documentation/api/request-data/browse-data/#read-the-browse-definition
 */
final readonly class BrowseDefinition
{
    /**
     * @param list<BrowseDefinitionColumn> $columns
     */
    public function __construct(
        public CompanyReference $company,
        /** E.g. `030_3`. */
        public string $code,
        /** In the language of the user, e.g. `Grootboek (details) (v3)`. */
        public string $name,
        public string $shortName,
        public bool $visible,
        public array $columns,
    ) {
    }

    public static function fromXml(XmlElement $browse, CompanyReference $company): self
    {
        if ($browse->element->localName !== 'browse') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <browse> response, got <%s>.',
                $browse->element->localName,
            ));
        }

        $office = $browse->string('office');

        if ($office !== $company->code) {
            throw new UnexpectedResponseException(sprintf(
                'Asked for a browse definition of company %s, got %s.',
                $company->code,
                $office,
            ));
        }

        $code = $browse->string('code');
        $columns = $browse->child('columns');

        if ($columns->attribute('code') !== $code) {
            throw new UnexpectedResponseException(sprintf(
                '%s/@code is %s, expected %s.',
                $columns->path,
                $columns->attribute('code'),
                $code,
            ));
        }

        return new self(
            company: $company,
            code: $code,
            name: $browse->string('name'),
            shortName: $browse->string('shortname'),
            visible: $browse->bool('visible'),
            columns: array_map(BrowseDefinitionColumn::fromXml(...), $columns->list('column')),
        );
    }

    /**
     * The columns that can be a selection criterion (`ask`).
     *
     * @return list<BrowseDefinitionColumn>
     */
    public function criteria(): array
    {
        return array_values(array_filter(
            $this->columns,
            static fn (BrowseDefinitionColumn $column): bool => $column->ask,
        ));
    }

    /**
     * The column of a field, or null when the browse code does not have the field.
     */
    public function column(string $field): ?BrowseDefinitionColumn
    {
        foreach ($this->columns as $column) {
            if ($column->field === $field) {
                return $column;
            }
        }

        return null;
    }
}
