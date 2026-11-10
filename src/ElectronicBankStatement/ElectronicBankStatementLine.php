<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\Transaction\TransactionXml;

/**
 * A line of an imported electronic bank statement, with the dimensions Twinfield allocated it to,
 * e.g. dim1 `1100` (debtors) and dim2 `1075` (the customer), or the suspense account.
 *
 * Optional elements are null when Twinfield leaves them out or empty; the response example of
 * the documentation, for instance, has no `contraaccount` or `contraiban`.
 */
final readonly class ElectronicBankStatementLine
{
    /**
     * @param array<int, DimensionReference> $dimensions Keyed by position 1 to 4.
     */
    public function __construct(
        /** `Credit`: money received by the bank; `Debit`: money paid by the bank. */
        public DebitCredit $debitCredit,
        /** Amount of the line, more than zero. */
        public Amount $value,
        public ?string $description,
        /** Transaction type code of the bank, e.g. `N100`. */
        public ?string $type,
        public ?string $reference,
        public ?string $contraIban,
        public ?string $contraAccount,
        public ?string $endToEndId,
        public ?string $returnReason,
        public array $dimensions,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $line): self
    {
        $dimensions = [];

        for ($position = 1; $position <= 4; $position++) {
            $dimension = TransactionXml::optionalDimension($line, 'dim' . $position);

            if ($dimension !== null) {
                $dimensions[$position] = $dimension;
            }
        }

        return new self(
            debitCredit: $line->enum('debitcredit', DebitCredit::class),
            value: TransactionXml::amount($line, 'value'),
            description: self::text($line, 'description'),
            type: self::text($line, 'type'),
            reference: self::text($line, 'reference'),
            contraIban: self::text($line, 'contraiban'),
            contraAccount: self::text($line, 'contraaccount'),
            endToEndId: self::text($line, 'endtoendid'),
            returnReason: self::text($line, 'returnreason'),
            dimensions: $dimensions,
        );
    }

    /**
     * The value with its sign as seen from the bank account: positive when money came in.
     */
    public function signedValue(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->value : $this->value->negate();
    }

    /**
     * Dimension 1 to 4, or null when Twinfield did not allocate the line at that position.
     */
    public function dimension(int $position): ?DimensionReference
    {
        if ($position < 1 || $position > 4) {
            throw new InvalidArgumentException(sprintf('Dimension position must be 1 to 4, %d given.', $position));
        }

        return $this->dimensions[$position] ?? null;
    }

    private static function text(XmlElement $line, string $name): ?string
    {
        $text = TransactionXml::optionalString($line, $name);

        return $text === '' ? null : $text;
    }
}
