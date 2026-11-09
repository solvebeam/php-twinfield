<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * A dimension type of the company with its settings, as Twinfield returns it.
 *
 *   $type = $twinfield->dimensionTypes()->get(DimensionTypeCode::AccountsReceivable);
 *   echo $type->name, ' ', $type->mask, PHP_EOL;          // e.g. "Debiteuren 1[0-9][0-9][0-9]"
 *   $type->mask->matches('1010');                          // true
 *   echo $type->addressLabels->field4, PHP_EOL;            // e.g. "BTW nummer"
 *
 * To change the name, mask or address labels, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-types/
 */
final readonly class DimensionType
{
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        /** Dimension type code, e.g. `DEB`; see DimensionTypeCode for the standard ones. */
        public string $code,
        public string $name,
        /** Short name (read-only); null when it is not set, as for AST in a new company. */
        public ?string $shortName,
        /** Mask that the codes of the dimensions must match. */
        public CodeMask $mask,
        public DimensionTypeLevels $levels,
        public AddressLabels $addressLabels,
        /** Number of times the dimension type was changed. */
        public int $touched,
    ) {
        foreach (['code' => $code, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Dimension type %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Dimension type touched count must be 0 or more.');
        }
    }

    /**
     * From a `<dimensiontype>` element of a response.
     */
    public static function fromXml(XmlElement $type, Organisation $organisation): self
    {
        if ($type->element->localName !== 'dimensiontype') {
            throw $type->invalid('<dimensiontype>');
        }

        return new self(
            company: new CompanyReference($organisation, $type->string('office')),
            code: $type->string('code'),
            name: $type->string('name'),
            shortName: $type->nullableString('shortname'),
            mask: new CodeMask($type->string('mask')),
            levels: DimensionTypeLevels::fromXml($type->child('levels')),
            addressLabels: AddressLabels::fromXml($type->child('address')),
            touched: $type->int('touched'),
        );
    }

    /**
     * The standard type this is, or null for another code.
     */
    public function standardCode(): ?DimensionTypeCode
    {
        return DimensionTypeCode::tryFrom($this->code);
    }

    /**
     * Input with all values of this dimension type, to change and pass to update().
     *
     *   $types->update($type->edit()->addressLabel(6, 'IBAN'));
     */
    public function edit(): DimensionTypeInput
    {
        return DimensionTypeInput::from($this);
    }
}
