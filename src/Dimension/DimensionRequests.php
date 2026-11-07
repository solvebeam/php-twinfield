<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Dimension;

use Closure;
use LogicException;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Requests that are the same for every dimension type: search, read, save, delete and
 * activate. Dimension resources such as activities and cost centers use it.
 *
 * @internal
 */
final readonly class DimensionRequests
{
    public function __construct(
        private Twinfield $twinfield,
        /** Dimension type, e.g. `ACT`. */
        private string $type,
        /** Name of one dimension in messages, e.g. `Activity`. */
        private string $singular,
        /** Name of the dimensions in messages, e.g. `Activities`. */
        private string $plural,
    ) {
    }

    /**
     * Finder search on code and name for this dimension type.
     *
     * @template T of object
     *
     * @param Closure(list<string>, int): T $mapRow
     *
     * @return CodeNameSearch<T>
     */
    public function search(string $pattern, Closure $mapRow): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::Dimensions)->option('dimtype', $this->type);

        return (new CodeNameSearch($query, $mapRow))->pattern($pattern);
    }

    /**
     * The `<dimension>` element of the dimension.
     *
     * @throws DimensionNotFoundException When there is no dimension with this code.
     */
    public function read(string $code): XmlElement
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'dimensions');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'dimtype', $this->type);
        $xml->add($xml->root(), 'code', $code);

        return XmlElement::root($this->send($xml, $code));
    }

    /**
     * Sends a `<dimension>` request and returns the `<dimension>` element of the response.
     *
     * Errors are not mapped to DimensionNotFoundException: saving creates a missing dimension,
     * so an error on the code (e.g. a code outside the code format) is a refusal, not "not found".
     *
     * @throws ProcessXmlException When Twinfield refuses the dimension.
     */
    public function save(XmlBuilder $xml): XmlElement
    {
        return XmlElement::root($this->twinfield->processXml($xml->toXml()));
    }

    /**
     * Deletes the dimension. When it is in use, Twinfield hides it instead.
     *
     * @throws DimensionNotFoundException When there is no dimension with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $status = $this->changeStatus($code, DimensionStatus::Deleted);

        return match ($status) {
            DimensionStatus::Deleted => DeleteResult::Deleted,
            DimensionStatus::Hidden => DeleteResult::Hidden,
            DimensionStatus::Active => throw new UnexpectedResponseException(sprintf(
                '%s %s is still active after deleting it.',
                $this->singular,
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden dimension active again.
     *
     * @throws DimensionNotFoundException When there is no dimension with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, DimensionStatus::Active);
    }

    /**
     * The company code of the client.
     *
     * @throws LogicException When the client has no company.
     */
    public function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException(sprintf('%s belong to a company: use company() first.', $this->plural));
    }

    private function changeStatus(string $code, DimensionStatus $status): DimensionStatus
    {
        $xml = XmlBuilder::create('dimension', ['status' => $status->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'type', $this->type);
        $xml->add($xml->root(), 'code', $code);

        return XmlElement::root($this->send($xml, $code))->enumAttribute('status', DimensionStatus::class);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw DimensionNotFoundException::fromProcessXml($exception, $this->type, $code) ?? $exception;
        }
    }
}
