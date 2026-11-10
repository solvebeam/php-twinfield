<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Matching;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\TransactionLineReference;

/**
 * A match set of existing transaction lines, e.g. a sales invoice and the payment of it. Send it
 * with MatchingResource::match(). Immutable: every method returns a new set.
 *
 *   MatchInput::customers(new DateTimeImmutable('2026-11-10'))
 *       ->line('KAS', 202600014, 2)
 *       ->line('VRK', 202600070, 1, matchValue: '119.00');
 *
 * To match a line while creating a bank or cash transaction, use DetailLineInput::match() with
 * a Transaction\MatchSetInput instead: that is another request.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/matching/
 */
final class MatchInput
{
    /** @var list<MatchInputLine> */
    private array $lines = [];

    private function __construct(
        public readonly MatchCode $matchCode,
        public readonly DateTimeImmutable $matchDate,
    ) {
    }

    public static function create(MatchCode $matchCode, DateTimeInterface $matchDate): self
    {
        return new self($matchCode, DateTimeImmutable::createFromInterface($matchDate));
    }

    /**
     * Lines of a customer (match code 170).
     */
    public static function customers(DateTimeInterface $matchDate): self
    {
        return self::create(MatchCode::Customers, $matchDate);
    }

    /**
     * Lines of a supplier (match code 270).
     */
    public static function suppliers(DateTimeInterface $matchDate): self
    {
        return self::create(MatchCode::Suppliers, $matchDate);
    }

    /**
     * Lines of a project (match code 370).
     */
    public static function projects(DateTimeInterface $matchDate): self
    {
        return self::create(MatchCode::Projects, $matchDate);
    }

    /**
     * Lines on suspense accounts (match code 070).
     */
    public static function suspenseAccounts(DateTimeInterface $matchDate): self
    {
        return self::create(MatchCode::SuspenseAccounts, $matchDate);
    }

    /**
     * Adds a transaction line.
     *
     * @param string $dayBook Day book (transaction type) code, e.g. `VRK`.
     * @param int $number Transaction number, e.g. `202600070`.
     * @param int $line Line number, e.g. 1 for the total line of an invoice.
     * @param Amount|numeric-string|null $matchValue Only for a partial payment, e.g. `'119.00'`;
     *                                               negative on a credit line.
     * @param WriteOff|null $writeOff Only for an exchange rate difference, a write-off or a discount.
     *
     * @throws InvalidArgumentException For an invalid line, or a line that is already in the set.
     */
    public function line(
        string $dayBook,
        int $number,
        int $line,
        Amount|string|null $matchValue = null,
        ?WriteOff $writeOff = null,
    ): self {
        $new = new MatchInputLine($dayBook, $number, $line, Amount::ofNullable($matchValue), $writeOff);

        foreach ($this->lines as $existing) {
            if ($existing->isSameLine($new)) {
                throw new InvalidArgumentException(sprintf(
                    'Line %d of %s %d is already in the match set.',
                    $line,
                    $dayBook,
                    $number,
                ));
            }
        }

        $set = clone $this;
        $set->lines[] = $new;

        return $set;
    }

    /**
     * Adds a transaction line by its reference, e.g. from a browse data row.
     *
     * @param Amount|numeric-string|null $matchValue
     *
     * @throws LogicException For a line of a draft transaction, which has no number yet.
     */
    public function lineReference(
        TransactionLineReference $reference,
        Amount|string|null $matchValue = null,
        ?WriteOff $writeOff = null,
    ): self {
        $transaction = $reference->transaction();

        return $this->line($transaction->dayBook, $transaction->number, $reference->line, $matchValue, $writeOff);
    }

    /**
     * @return list<MatchInputLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * The `<match>` request for the company with this office code.
     *
     * @throws LogicException When the set has fewer than two lines.
     *
     * @internal
     */
    public function toXml(string $office): string
    {
        if (count($this->lines) < 2) {
            throw new LogicException('A match set needs at least two lines: add them with line().');
        }

        $xml = XmlBuilder::create('match');
        $set = $xml->add($xml->root(), 'set');

        $xml->add($set, 'office', $office);
        $xml->add($set, 'matchcode', $this->matchCode->value);
        $xml->add($set, 'matchdate', XmlValue::date($this->matchDate));

        $lines = $xml->add($set, 'lines');

        foreach ($this->lines as $line) {
            $element = $xml->add($lines, 'line');

            $xml->add($element, 'transcode', $line->dayBook);
            $xml->add($element, 'transnumber', (string) $line->number);
            $xml->add($element, 'transline', (string) $line->line);

            if ($line->matchValue !== null) {
                $xml->add($element, 'matchvalue', $line->matchValue->value);
            }

            if ($line->writeOff !== null) {
                $xml->add($element, 'writeoff', $line->writeOff->amount->value, [
                    'type' => $line->writeOff->type->value,
                ]);
            }
        }

        return $xml->toXml();
    }
}
