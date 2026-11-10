<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Transaction;

use DateTimeImmutable;
use DateTimeInterface;
use DOMElement;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * A match set to send with a detail line: the lines of other transactions this line pays,
 * e.g. the invoice of a customer. Immutable: every method returns a new set.
 *
 *   DetailLineInput::credit('1300', '435.55')
 *       ->customer('1000')
 *       ->invoice('2026-0042')
 *       ->match(
 *           MatchSetInput::create(new DateTimeImmutable('2026-11-03'))
 *               ->line('VRK', 202600012, 1, '435.55'),
 *       );
 *
 * The match value of the set is the sum of its lines.
 *
 * @see https://developers.twinfield.com/documentation/api/transaction-data/bank-transactions/
 */
final class MatchSetInput
{
    /** @var list<MatchLine> */
    private array $lines = [];

    private function __construct(
        private readonly DateTimeImmutable $matchDate,
    ) {
    }

    public static function create(DateTimeInterface $matchDate): self
    {
        return new self(DateTimeImmutable::createFromInterface($matchDate));
    }

    /**
     * Adds a line of another transaction to match with, e.g. line 1 (the total line) of a sales invoice.
     *
     * @param string $dayBook Day book (transaction type) code of that transaction, e.g. `VRK`.
     * @param int $number Transaction number, e.g. `202600012`.
     * @param int $line Line ID within that transaction.
     * @param Amount|numeric-string $matchValue Amount to match, e.g. `'435.55'`.
     */
    public function line(string $dayBook, int $number, int $line, Amount|string $matchValue): self
    {
        XmlValue::code($dayBook);

        if ($number < 1) {
            throw new InvalidArgumentException(
                sprintf('Match line transaction number must be positive, %d given.', $number),
            );
        }

        if ($line < 1) {
            throw new InvalidArgumentException(sprintf('Match line ID must be positive, %d given.', $line));
        }

        $matchValue = Amount::of($matchValue);

        if ($matchValue->isZero()) {
            throw new InvalidArgumentException('Match value must not be zero.');
        }

        $set = clone $this;
        $set->lines[] = new MatchLine($dayBook, $number, $line, $matchValue);

        return $set;
    }

    public function matchDate(): DateTimeImmutable
    {
        return $this->matchDate;
    }

    /**
     * @return list<MatchLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Sum of the match values of the lines.
     */
    public function matchValue(): Amount
    {
        return Amount::sum(array_map(static fn (MatchLine $line): Amount => $line->matchValue, $this->lines));
    }

    /**
     * @throws LogicException When the set has no lines.
     */
    public function appendTo(XmlBuilder $xml, DOMElement $matches): void
    {
        if ($this->lines === []) {
            throw new LogicException('A match set needs at least one line: add one with line().');
        }

        $set = $xml->add($matches, 'set');

        $xml->add($set, 'matchdate', XmlValue::date($this->matchDate));

        $lines = $xml->add($set, 'lines');

        foreach ($this->lines as $line) {
            $element = $xml->add($lines, 'line');

            $xml->add($element, 'code', $line->dayBook);
            $xml->add($element, 'number', (string) $line->number);
            $xml->add($element, 'line', (string) $line->line);
            // Fixed value in the documentation.
            $xml->add($element, 'method', 'payment');
            $xml->add($element, 'matchvalue', $line->matchValue->value);
        }

        $xml->add($set, 'matchvalue', $this->matchValue()->value);
    }
}
