<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\CostCenter;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A cost center to create or update. Immutable: every method returns a new input.
 *
 *   $input = CostCenterInput::create('100', 'Sales');
 *
 * For an existing cost center, start from `$costCenter->edit()`.
 *
 * Only the name can be set: Twinfield documents the other dimension fields (short name,
 * periods, website, chamber of commerce and VAT number) as "not in use" for cost centers.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/cost-centers/
 */
final readonly class CostCenterInput
{
    private function __construct(
        public string $code,
        public string $name,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Cost center name must not be empty.');
        }

        XmlValue::maxLength('cost center name', $name, 80);
    }

    /**
     * @param string $code Code in the format of the KPL dimension type of the company, e.g. `100`.
     * @param string $name Name, at most 80 characters.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name);
    }

    public function name(string $name): self
    {
        return new self($this->code, $name);
    }

    /**
     * The `<dimension>` request to save this cost center in a company.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', 'KPL');
        $xml->add($dimension, 'code', $this->code);
        $xml->add($dimension, 'name', $this->name);

        return $xml;
    }
}
