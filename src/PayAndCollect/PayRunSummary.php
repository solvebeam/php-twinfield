<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * A payment or collection in a pay run (`SummaryInfo`): the invoices of one relation in one
 * currency, summarised according to the method of the run.
 */
final readonly class PayRunSummary
{
    /**
     * @param list<PayRunDetail> $details
     */
    public function __construct(
        /** Identifier within the run; with relation and currency the key of the summary. */
        public int $id,
        /** Code of the supplier (payment run) or customer (direct debit run). */
        public string $relation,
        public string $relationName,
        /** Bank account of the relation; null when Twinfield leaves it out. */
        public ?PayRunBank $relationBank,
        /** Currency code, e.g. `EUR`. */
        public string $currency,
        public string $currencyName,
        /** Payment reference, formed as set in the pay file settings. */
        public string $reference,
        /** False when the summary is excluded from the run. */
        public bool $included,
        public array $details,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $summary): self
    {
        $bank = $summary->nullableChild('RelationBank');

        return new self(
            id: $summary->int('Id'),
            relation: PayAndCollectXml::string($summary, 'Relation'),
            relationName: PayAndCollectXml::string($summary, 'RelationName'),
            relationBank: $bank === null ? null : PayRunBank::fromResult($bank),
            currency: PayAndCollectXml::string($summary, 'Currency'),
            currencyName: PayAndCollectXml::string($summary, 'CurrencyName'),
            reference: PayAndCollectXml::string($summary, 'Reference'),
            included: $summary->bool('Included'),
            // Left out when the summary has no details.
            details: $summary->nullableChild('Details') === null
                ? []
                : array_map(PayRunDetail::fromResult(...), $summary->list('Details', 'DetailInfo')),
        );
    }

    public function detail(int $id): ?PayRunDetail
    {
        foreach ($this->details as $detail) {
            if ($detail->id === $id) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Sum of the values of the included details.
     */
    public function value(): Amount
    {
        return Amount::sum(array_map(
            static fn (PayRunDetail $detail): Amount => $detail->value,
            array_filter($this->details, static fn (PayRunDetail $detail): bool => $detail->included),
        ));
    }
}
