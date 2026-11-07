<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\DimensionType;

use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;

/**
 * Dimension types of the company (BAS, PNL, DEB, CRD, KPL, AST, PRJ, ACT) and their settings:
 * name, code mask, levels and address labels.
 *
 *   $types = $twinfield->company('1000')->dimensionTypes();
 *
 *   foreach ($types->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $customers = $types->get(DimensionTypeCode::AccountsReceivable);
 *   $customers->mask->matches('1010');      // whether a new customer code is allowed
 *
 *   $types->update($customers->edit()->addressLabel(6, 'IBAN'));
 *
 *   // Types per financial level, and the type(s) of a dimension code
 *   $types->search()->financialLevel(2)->all();   // CRD, DEB, KPL
 *   $types->search()->ofDimension('1000')->all(); // e.g. BAS and DEB
 *
 * Twinfield has a fixed set of dimension types: they can be read and changed, not created
 * or deleted. Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/dimension-types/
 */
final readonly class DimensionTypeResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search dimension types by code and name, e.g. `search()->financialLevel(2)->get()`.
     *
     * Finder type `DMT`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<DimensionTypeSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::DimensionTypes);

        return (new CodeNameSearch($query, DimensionTypeSummary::fromFinderRow(...)))->pattern($pattern);
    }

    /**
     * Whether the company has a dimension type with exactly this code (one Finder request).
     */
    public function exists(DimensionTypeCode|string $code): bool
    {
        $code = self::code($code);

        foreach ($this->search($code)->inCode()->get() as $summary) {
            if ($summary->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * The dimension type with its settings, in one request.
     *
     * Twinfield has no `<read>` for dimension types: a `<dimensiontype>` with only office and
     * code reads it, and an unknown code is refused, so a read never creates or changes anything.
     *
     * @throws DimensionTypeNotFoundException When there is no dimension type with this code.
     */
    public function get(DimensionTypeCode|string $code): DimensionType
    {
        $code = self::code($code);

        $xml = XmlBuilder::create('dimensiontype');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return DimensionType::fromXml(XmlElement::root($this->send($xml, $code)));
    }

    /**
     * The dimension type, or null when there is no dimension type with this code.
     */
    public function find(DimensionTypeCode|string $code): ?DimensionType
    {
        try {
            return $this->get($code);
        } catch (DimensionTypeNotFoundException) {
            return null;
        }
    }

    /**
     * Saves the name, mask and address labels. Start from `$type->edit()`.
     *
     * @throws DimensionTypeNotFoundException When there is no dimension type with this code.
     * @throws ProcessXmlException When Twinfield refuses the changes, e.g. a label that is too long.
     */
    public function update(DimensionTypeInput $type): DimensionType
    {
        return DimensionType::fromXml(XmlElement::root($this->send($type->toXml($this->company()), $type->code)));
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw DimensionTypeNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Dimension types belong to a company: use company() first.');
    }

    private static function code(DimensionTypeCode|string $code): string
    {
        return $code instanceof DimensionTypeCode ? $code->value : $code;
    }
}
