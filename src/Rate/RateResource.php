<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rate;

use LogicException;
use SolveBeam\Twinfield\Dimension\DeleteResult;
use SolveBeam\Twinfield\Finder\CodeNameSearch;
use SolveBeam\Twinfield\Finder\FinderType;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlResult;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Rates of Twinfield Time & Expenses: the cost and selling price of an hour or of a quantity
 * such as a kilometre, per date range. Activities, projects and their quantities refer to a
 * rate by its code.
 *
 *   $rates = $twinfield->company('1000')->rates();
 *
 *   foreach ($rates->search()->rateType(RateType::Time)->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $rate = $rates->get('CONSULT');
 *
 *   $rates->create(
 *       RateInput::quantity('KM', 'Kilometres')
 *           ->price(new DateTimeImmutable('2026-01-01'), internal: '0.15', external: '0.23'),
 *   );
 *   $rates->update($rate->edit()->newPriceFrom(new DateTimeImmutable('2026-07-01'), '60', '105'));
 *   $rates->delete('KM');
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/rates/
 */
final readonly class RateResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search rates by code and name, e.g. `search('T*')->rateType(RateType::Time)->get()`.
     *
     * Finder type `TRT`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<RateSummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::Rates);

        return CodeNameSearch::forCompany($query, RateSummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * The rate with all its prices.
     *
     * @throws RateNotFoundException When there is no rate with this code.
     */
    public function get(string $code): Rate
    {
        $xml = XmlBuilder::create('read');
        $xml->add($xml->root(), 'type', 'projectrate');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return Rate::fromXml(XmlElement::root($this->send($xml, $code)), $this->twinfield->organisation());
    }

    /**
     * The rate, or null when there is no rate with this code.
     */
    public function find(string $code): ?Rate
    {
        try {
            return $this->get($code);
        } catch (RateNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new rate. Reads first, so an existing rate is never overwritten.
     *
     * @throws RateAlreadyExistsException When the code is already in use.
     */
    public function create(RateInput $rate): Rate
    {
        if ($this->find($rate->code) !== null) {
            throw new RateAlreadyExistsException($rate->code);
        }

        return $this->save($rate);
    }

    /**
     * Updates an existing rate with all values of the input. Start from `$rate->edit()` to keep
     * the values and prices you do not change.
     *
     * @throws RateNotFoundException When there is no rate with this code.
     */
    public function update(RateInput $rate): Rate
    {
        $this->get($rate->code);

        return $this->save($rate);
    }

    /**
     * Creates the rate, or updates it when the code exists (no existence check). Reads it back
     * afterwards, so you always get the complete rate.
     *
     * @throws ProcessXmlException When Twinfield refuses the rate.
     */
    public function save(RateInput $rate): Rate
    {
        // Saving creates a missing rate, so errors are not mapped to RateNotFoundException.
        $this->twinfield->processXml($rate->toXml($this->company())->toXml());

        return $this->get($rate->code);
    }

    /**
     * Deletes the rate. When it is in use, Twinfield hides it instead.
     *
     * @throws RateNotFoundException When there is no rate with this code.
     */
    public function delete(string $code): DeleteResult
    {
        $status = $this->changeStatus($code, RateStatus::Deleted);

        return match ($status) {
            RateStatus::Deleted => DeleteResult::Deleted,
            RateStatus::Hidden => DeleteResult::Hidden,
            RateStatus::Active => throw new UnexpectedResponseException(sprintf(
                'Rate %s is still active after deleting it.',
                $code,
            )),
        };
    }

    /**
     * Makes a deleted or hidden rate active again.
     *
     * @throws RateNotFoundException When there is no rate with this code.
     */
    public function activate(string $code): void
    {
        $this->changeStatus($code, RateStatus::Active);
    }

    private function changeStatus(string $code, RateStatus $status): RateStatus
    {
        $xml = XmlBuilder::create('projectrate', ['status' => $status->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return XmlElement::root($this->send($xml, $code))->enumAttribute('status', RateStatus::class);
    }

    private function send(XmlBuilder $xml, string $code): ProcessXmlResult
    {
        try {
            return $this->twinfield->processXml($xml->toXml());
        } catch (ProcessXmlException $exception) {
            throw RateNotFoundException::fromProcessXml($exception, $code) ?? $exception;
        }
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Rates belong to a company: use company() first.');
    }
}
