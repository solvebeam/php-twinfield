<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Currency;

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
 * Currencies of the company and their exchange rates.
 *
 *   $currencies = $twinfield->company('1000')->currencies();
 *
 *   foreach ($currencies->search()->all() as $summary) {
 *       echo $summary->code, ' ', $summary->name, PHP_EOL;
 *   }
 *
 *   $usd = $currencies->get('USD');
 *   $rate = $usd->rateOn(new DateTimeImmutable('2026-03-15'))?->rate;
 *
 *   $currencies->create(
 *       CurrencyInput::create('GBP', 'Pound sterling')->rate(new DateTimeImmutable('2026-01-01'), '0.8650'),
 *   );
 *   $currencies->update($usd->edit()->rate(new DateTimeImmutable('2026-07-01'), '1.0912'));
 *   $currencies->delete('GBP');
 *
 * Twinfield has no `<read>` for currencies, and a `<currency>` request for an unknown code
 * creates it. So get(), update() and delete() first check in the Finder that the code exists:
 * a read never creates a currency.
 *
 * Twinfield refusals are thrown as ProcessXmlException, with the messages per element.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/currencies/
 */
final readonly class CurrencyResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * Search currencies by code and name, e.g. `search('U*')->inCode()->get()`.
     *
     * Finder type `CUR`; the company comes from the client, so no `office` option is sent.
     *
     * @param string $pattern `*` matches anything, `?` one character.
     *
     * @return CodeNameSearch<CurrencySummary>
     */
    public function search(string $pattern = '*'): CodeNameSearch
    {
        $query = $this->twinfield->finder(FinderType::Currencies);

        return CodeNameSearch::forCompany($query, CurrencySummary::fromFinderRow(...))->pattern($pattern);
    }

    /**
     * Whether the company has a currency with exactly this code (one Finder request).
     */
    public function exists(string $code): bool
    {
        foreach ($this->search($code)->inCode()->get() as $summary) {
            if ($summary->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * The currency with its rates. Two requests: a Finder check, then the currency itself.
     *
     * @throws CurrencyNotFoundException When there is no currency with this code.
     */
    public function get(string $code): Currency
    {
        if (!$this->exists($code)) {
            throw new CurrencyNotFoundException($code);
        }

        return $this->read($code);
    }

    /**
     * The currency, or null when there is no currency with this code.
     */
    public function find(string $code): ?Currency
    {
        try {
            return $this->get($code);
        } catch (CurrencyNotFoundException) {
            return null;
        }
    }

    /**
     * Creates a new currency. Checks first, so an existing currency is never overwritten.
     *
     * @throws CurrencyAlreadyExistsException When the code is already in use.
     */
    public function create(CurrencyInput $currency): Currency
    {
        if ($this->exists($currency->code)) {
            throw new CurrencyAlreadyExistsException($currency->code);
        }

        return $this->save($currency);
    }

    /**
     * Updates an existing currency. Start from `$currency->edit()`, so the rates you do not
     * change are sent as they are.
     *
     * @throws CurrencyNotFoundException When there is no currency with this code.
     */
    public function update(CurrencyInput $currency): Currency
    {
        if (!$this->exists($currency->code)) {
            throw new CurrencyNotFoundException($currency->code);
        }

        return $this->save($currency);
    }

    /**
     * Creates the currency, or updates it when the code exists (no existence check).
     * Reads it back afterwards, so you always get all rates.
     *
     * @throws ProcessXmlException When Twinfield refuses the currency.
     */
    public function save(CurrencyInput $currency): Currency
    {
        $this->twinfield->processXml($currency->toXml($this->company())->toXml());

        return $this->read($currency->code);
    }

    /**
     * Deletes the currency.
     *
     * @throws CurrencyNotFoundException When there is no currency with this code.
     */
    public function delete(string $code): void
    {
        if (!$this->exists($code)) {
            throw new CurrencyNotFoundException($code);
        }

        $xml = XmlBuilder::create('currency', ['status' => CurrencyStatus::Deleted->value]);
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        $status = self::currency($this->twinfield->processXml($xml->toXml()))
            ->enumAttribute('status', CurrencyStatus::class);

        if ($status !== CurrencyStatus::Deleted) {
            throw new UnexpectedResponseException(sprintf(
                'Currency %s is still %s after deleting it.',
                $code,
                $status->value,
            ));
        }
    }

    /**
     * Reads a currency that exists: a `<currency>` with only office and code returns it.
     */
    private function read(string $code): Currency
    {
        $xml = XmlBuilder::create('currency');
        $xml->add($xml->root(), 'office', $this->company());
        $xml->add($xml->root(), 'code', $code);

        return Currency::fromXml(
            self::currency($this->twinfield->processXml($xml->toXml())),
            $this->twinfield->organisation(),
        );
    }

    /**
     * The `<currency>` element of a response; Twinfield may wrap it in `<currencies>`,
     * as in its documentation.
     */
    private static function currency(ProcessXmlResult $result): XmlElement
    {
        $root = XmlElement::root($result);

        if ($root->element->localName === 'currency') {
            return $root;
        }

        if ($root->element->localName !== 'currencies') {
            throw new UnexpectedResponseException(sprintf('Expected <currency>, got <%s>.', $root->element->localName));
        }

        $currencies = $root->list('currency');

        if (count($currencies) !== 1) {
            throw new UnexpectedResponseException(sprintf(
                'Expected one <currency> in <currencies>, got %d.',
                count($currencies),
            ));
        }

        return $currencies[0];
    }

    private function company(): string
    {
        return $this->twinfield->companyCode()
            ?? throw new LogicException('Currencies belong to a company: use company() first.');
    }
}
