<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ElectronicBankStatement;

use InvalidArgumentException;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DebitCredit;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * Where (part of) a bank statement line is booked: a general ledger account, a customer, a
 * supplier or the suspense account. A line can be split over several allocations.
 *
 * The amount is positive; the side is the side of the contra booking. Money received is booked
 * `Credit` (e.g. on the customer), money paid `Debit`, so the allocations of a line add up to
 * the line amount when you count credit as positive.
 */
final readonly class BankStatementAllocation
{
    /**
     * @param array<int, string> $dimensions Dimension codes keyed by position 1 to 6; only filled positions.
     * @param list<BankStatementVatLine> $vatLines
     * @param list<BankStatementMatching> $matchings
     */
    public function __construct(
        public BankStatementAllocationType $type,
        public Amount $amount,
        /** Amount in the base currency of the company. */
        public Amount $baseAmount,
        public DebitCredit $debitCredit,
        public array $dimensions,
        /** Description format, e.g. `[editable_description]`; null when there is none. */
        public ?string $descriptionFormat,
        /** Whether the description format is saved in the allocation rule. */
        public bool $saveDescriptionFormatToAllocationRule,
        public ?string $chequeNumber,
        public ?int $payingInSlipNumber,
        public array $vatLines,
        public array $matchings,
    ) {
        foreach ($dimensions as $position => $code) {
            if ($position < 1 || $position > 6 || $code === '') {
                throw new InvalidArgumentException('Dimensions must be non-empty codes at positions 1 to 6.');
            }
        }
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $allocation): self
    {
        $dimensions = [];

        for ($position = 1; $position <= 6; $position++) {
            $code = $allocation->nullableString('Dimension' . $position);

            if ($code !== null) {
                $dimensions[$position] = $code;
            }
        }

        $sense = $allocation->string('Sense');

        return new self(
            type: $allocation->enum('Type', BankStatementAllocationType::class),
            amount: $allocation->amount('Amount'),
            baseAmount: $allocation->amount('BaseAmount'),
            debitCredit: match ($sense) {
                'Debit' => DebitCredit::Debit,
                'Credit' => DebitCredit::Credit,
                default => throw new UnexpectedResponseException(sprintf(
                    '%s/Sense is not Debit or Credit: "%s".',
                    $allocation->path,
                    $sense,
                )),
            },
            dimensions: $dimensions,
            descriptionFormat: $allocation->nullableString('DescriptionFormat'),
            saveDescriptionFormatToAllocationRule: $allocation->bool('SaveDescriptionFormatToAllocationRule'),
            chequeNumber: $allocation->nullableString('ChequeNumber'),
            payingInSlipNumber: $allocation->nullableWholeNumber('PayingInSlipNumber'),
            vatLines: array_map(
                BankStatementVatLine::fromResult(...),
                $allocation->list('VatLines', 'BankStatementVatLine'),
            ),
            matchings: array_map(
                BankStatementMatching::fromResult(...),
                $allocation->list('Matchings', 'BankStatementMatching'),
            ),
        );
    }

    /**
     * Dimension 1 to 6, or null when the position is empty.
     */
    public function dimension(int $position): ?string
    {
        if ($position < 1 || $position > 6) {
            throw new InvalidArgumentException(sprintf('Dimension position must be 1 to 6, %d given.', $position));
        }

        return $this->dimensions[$position] ?? null;
    }

    /**
     * The amount with its sign as seen from the bank account: positive for credit (money received).
     */
    public function signedAmount(): Amount
    {
        return $this->debitCredit === DebitCredit::Credit ? $this->amount : $this->amount->negate();
    }
}
