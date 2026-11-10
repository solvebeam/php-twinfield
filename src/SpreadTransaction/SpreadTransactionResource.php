<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\SpreadTransaction;

use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;
use SolveBeam\Twinfield\ProcessXml\ProcessXmlException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\TransactionReference;
use SolveBeam\Twinfield\Twinfield;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Spreads of sales and purchase transactions: read, spread a final or a provisional transaction
 * over a number of periods (the whole transaction or per detail line), and undo that.
 *
 *   $spreads = $twinfield->company('1000')->spreadTransactions();
 *
 *   $spread = $spreads->postFinal('INK', 202600001, '2500', Period::of(2026, 1), Period::of(2026, 12));
 *
 *   foreach ($spread->spreadTransactions as $transaction) {
 *       echo $transaction->dayBook, ' ', $transaction->number, PHP_EOL;
 *   }
 *
 * Twinfield makes the spread transactions itself: one that moves the amounts of the detail lines
 * to the intermediate account in the period of the original transaction, and one or more that
 * spread them from there over the periods.
 *
 * Twinfield refusals are thrown as ProcessXmlException.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/spread-transactions/
 */
final readonly class SpreadTransactionResource
{
    public function __construct(
        private Twinfield $twinfield,
    ) {
    }

    /**
     * The spread of a sales or purchase transaction: its settings and spread transactions.
     *
     * @param string $dayBook Sales or purchase day book code, e.g. `INK`.
     *
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function get(string $dayBook, int $number): Spread
    {
        return $this->send('getspreadtransactioninfo', $dayBook, $number);
    }

    /**
     * Spreads a final transaction: all detail lines over the same periods.
     *
     * @param string $intermediateAccount Balance sheet account the amounts are spread from, e.g. `2500`.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a provisional transaction.
     */
    public function postFinal(
        string $dayBook,
        int $number,
        string $intermediateAccount,
        Period $startPeriod,
        Period $endPeriod,
    ): Spread {
        $settings = SpreadSettings::forTransaction($intermediateAccount, $startPeriod, $endPeriod);

        return $this->send('postfinal', $dayBook, $number, $settings);
    }

    /**
     * Spreads a provisional transaction: all detail lines over the same periods.
     *
     * @param string $intermediateAccount Balance sheet account the amounts are spread from, e.g. `2500`.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a final transaction.
     */
    public function postProvisional(
        string $dayBook,
        int $number,
        string $intermediateAccount,
        Period $startPeriod,
        Period $endPeriod,
    ): Spread {
        $settings = SpreadSettings::forTransaction($intermediateAccount, $startPeriod, $endPeriod);

        return $this->send('postprovisional', $dayBook, $number, $settings);
    }

    /**
     * Spreads a final transaction with periods per detail line.
     *
     * When the periods of all lines are the period of the transaction itself, Twinfield removes
     * the spread instead, as `undo()` does.
     *
     * @param string $intermediateAccount Balance sheet account the amounts are spread from, e.g. `2500`.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a provisional transaction.
     */
    public function postFinalByLines(
        string $dayBook,
        int $number,
        string $intermediateAccount,
        SpreadLine ...$lines,
    ): Spread {
        $settings = SpreadSettings::byLines($intermediateAccount, ...$lines);

        return $this->send('postfinalbylines', $dayBook, $number, $settings);
    }

    /**
     * Spreads a provisional transaction with periods per detail line.
     *
     * When the periods of all lines are the period of the transaction itself, Twinfield removes
     * the spread instead, as `undo()` does.
     *
     * @param string $intermediateAccount Balance sheet account the amounts are spread from, e.g. `2500`.
     *
     * @throws ProcessXmlException When Twinfield refuses it, e.g. for a final transaction.
     */
    public function postProvisionalByLines(
        string $dayBook,
        int $number,
        string $intermediateAccount,
        SpreadLine ...$lines,
    ): Spread {
        $settings = SpreadSettings::byLines($intermediateAccount, ...$lines);

        return $this->send('postprovisionalbylines', $dayBook, $number, $settings);
    }

    /**
     * Removes the spread of a provisional transaction: the spread transactions, the settings and
     * the link.
     *
     * @throws ProcessXmlException When Twinfield refuses it.
     */
    public function undo(string $dayBook, int $number): void
    {
        $this->send('undo', $dayBook, $number);
    }

    private function send(string $action, string $dayBook, int $number, ?SpreadSettings $settings = null): Spread
    {
        if ($number < 1) {
            throw new InvalidArgumentException(sprintf('Transaction number must be 1 or more, got %d.', $number));
        }

        $company = $this->twinfield->companyReference();

        $xml = XmlBuilder::create('spread', ['action' => $action]);
        $original = $xml->add($xml->root(), 'original');
        $xml->add($original, 'office', $company->code);
        $xml->add($original, 'code', XmlValue::code($dayBook));
        $xml->add($original, 'number', (string) $number);

        if ($settings !== null) {
            self::writeSettings($xml, $settings);
        }

        $spread = XmlElement::root($this->twinfield->processXml($xml->toXml()));

        if ($spread->element->localName !== 'spread') {
            throw new UnexpectedResponseException(sprintf(
                'Expected a <spread> response, got <%s>.',
                $spread->element->localName,
            ));
        }

        $result = self::spread($spread, $company);

        if (!$result->original->equals(new TransactionReference($company, $dayBook, $number))) {
            throw new UnexpectedResponseException(sprintf(
                'Asked for the spread of %s %d in %s, got %s %d in %s.',
                $dayBook,
                $number,
                $company->code,
                $result->original->dayBook,
                $result->original->number,
                $result->original->company->code,
            ));
        }

        return $result;
    }

    private static function writeSettings(XmlBuilder $xml, SpreadSettings $settings): void
    {
        $element = $xml->add($xml->root(), 'settings');
        $xml->add($element, 'dim1', XmlValue::code($settings->intermediateAccount));

        if ($settings->startPeriod !== null && $settings->endPeriod !== null) {
            $xml->add($element, 'startperiod', (string) $settings->startPeriod);
            $xml->add($element, 'endperiod', (string) $settings->endPeriod);

            return;
        }

        $lines = $xml->add($element, 'linessettings');

        foreach ($settings->lines as $line) {
            $lineElement = $xml->add($lines, 'linesettings');
            $xml->add($lineElement, 'lineid', (string) $line->lineId);
            $xml->add($lineElement, 'startperiod', (string) $line->startPeriod);
            $xml->add($lineElement, 'endperiod', (string) $line->endPeriod);
        }
    }

    private static function spread(XmlElement $spread, CompanyReference $company): Spread
    {
        $settings = $spread->nullableChild('settings');

        // Twinfield leaves <spreadtransactions> out when there are none: after `undo`, and after a
        // spread by lines that removes the spread.
        $transactions = $spread->nullableChild('spreadtransactions')?->list('spreadtransaction') ?? [];

        return new Spread(
            original: TransactionReference::fromXml($spread->child('original'), $company),
            settings: $settings === null ? null : self::settings($settings),
            spreadTransactions: array_map(
                static fn (XmlElement $transaction): TransactionReference => TransactionReference::fromXml(
                    $transaction,
                    $company,
                ),
                $transactions,
            ),
        );
    }

    private static function settings(XmlElement $settings): SpreadSettings
    {
        $intermediateAccount = $settings->string('dim1');
        $lines = $settings->nullableChild('linessettings');

        try {
            if ($lines === null) {
                return SpreadSettings::forTransaction(
                    $intermediateAccount,
                    self::period($settings, 'startperiod'),
                    self::period($settings, 'endperiod'),
                );
            }

            return SpreadSettings::byLines($intermediateAccount, ...array_map(
                static fn (XmlElement $line): SpreadLine => new SpreadLine(
                    $line->int('lineid'),
                    self::period($line, 'startperiod'),
                    self::period($line, 'endperiod'),
                ),
                $lines->list('linesettings'),
            ));
        } catch (InvalidArgumentException $exception) {
            throw new UnexpectedResponseException(
                'spread/settings is not valid: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private static function period(XmlElement $parent, string $name): Period
    {
        try {
            return Period::fromString($parent->string($name));
        } catch (InvalidArgumentException) {
            throw $parent->child($name)->invalid('a period yyyy/pp');
        }
    }
}
