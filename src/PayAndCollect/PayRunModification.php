<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use InvalidArgumentException;
use SolveBeam\Twinfield\PayAndCollect\Request\DetailSettings;
use SolveBeam\Twinfield\PayAndCollect\Request\DetailSettingsList;
use SolveBeam\Twinfield\PayAndCollect\Request\SummarySettings;
use SolveBeam\Twinfield\PayAndCollect\Request\SummarySettingsList;
use SolveBeam\Twinfield\Transaction\Amount;

/**
 * Changes to a pay run before it is authorised: exclude summaries or invoices, or pay less of an
 * invoice. Send it with PayAndCollectResource::modify(). Immutable: every method returns a new
 * modification.
 *
 *   $modification = PayRunModification::of($run)
 *       ->excludeSummary(3)
 *       ->value(1, 2, '50.00');
 *
 * Summaries and details are identified by their IDs in the run (`$summary->id`, `$detail->id`).
 * The modification sends every summary and detail of the run, with the current values for the
 * ones that were not changed.
 *
 * @see https://developers.twinfield.com/documentation/api/miscellaneous/pay-and-collect/#modify
 */
final class PayRunModification
{
    /** @var array<int, bool> Included, by summary ID. */
    private array $summaries = [];

    /** @var array<int, array<int, array{value: Amount, included: bool, discountAmount: Amount}>> By summary and detail ID. */
    private array $details = [];

    private function __construct(
        public readonly PayRun $run,
    ) {
    }

    public static function of(PayRun $run): self
    {
        return new self($run);
    }

    public function excludeSummary(int $summaryId): self
    {
        return $this->includeSummary($summaryId, false);
    }

    public function includeSummary(int $summaryId, bool $included = true): self
    {
        $this->summary($summaryId);

        $modification = clone $this;
        $modification->summaries[$summaryId] = $included;

        return $modification;
    }

    public function excludeDetail(int $summaryId, int $detailId): self
    {
        return $this->includeDetail($summaryId, $detailId, false);
    }

    public function includeDetail(int $summaryId, int $detailId, bool $included = true): self
    {
        return $this->withDetail($summaryId, $detailId, ['included' => $included]);
    }

    /**
     * Pays or collects only part of an invoice.
     *
     * @param Amount|numeric-string $value Not zero, and not more than the original value.
     */
    public function value(int $summaryId, int $detailId, Amount|string $value): self
    {
        $value = Amount::of($value);
        $original = $this->detail($summaryId, $detailId)->originalValue;

        if ($value->isZero()) {
            throw new InvalidArgumentException(sprintf(
                'Value of detail %d of summary %d must not be zero: exclude the detail instead.',
                $detailId,
                $summaryId,
            ));
        }

        if ($value->absolute()->compare($original->absolute()) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Value %s of detail %d of summary %d is more than the original value %s.',
                $value,
                $detailId,
                $summaryId,
                $original,
            ));
        }

        return $this->withDetail($summaryId, $detailId, ['value' => $value]);
    }

    /**
     * @param Amount|numeric-string $discountAmount 0 or more.
     */
    public function discount(int $summaryId, int $detailId, Amount|string $discountAmount): self
    {
        $discountAmount = Amount::of($discountAmount);

        if ($discountAmount->isNegative()) {
            throw new InvalidArgumentException(sprintf(
                'Discount amount of detail %d of summary %d must not be negative, got %s.',
                $detailId,
                $summaryId,
                $discountAmount,
            ));
        }

        return $this->withDetail($summaryId, $detailId, ['discountAmount' => $discountAmount]);
    }

    /**
     * @internal
     */
    public function toSummaries(): SummarySettingsList
    {
        return new SummarySettingsList(array_map(
            fn (PayRunSummary $summary): SummarySettings => new SummarySettings(
                relation: $summary->relation,
                currency: $summary->currency,
                id: $summary->id,
                included: self::bool($this->summaries[$summary->id] ?? $summary->included),
                details: new DetailSettingsList(array_map(
                    function (PayRunDetail $detail) use ($summary): DetailSettings {
                        $changed = $this->details[$summary->id][$detail->id] ?? null;

                        return new DetailSettings(
                            id: $detail->id,
                            value: (string) ($changed['value'] ?? $detail->value),
                            included: self::bool($changed['included'] ?? $detail->included),
                            discountAmount: (string) ($changed['discountAmount'] ?? $detail->discountAmount),
                        );
                    },
                    $summary->details,
                )),
            ),
            $this->run->summaries,
        ));
    }

    /**
     * @param array{value?: Amount, included?: bool, discountAmount?: Amount} $changes
     */
    private function withDetail(int $summaryId, int $detailId, array $changes): self
    {
        $detail = $this->detail($summaryId, $detailId);
        $modification = clone $this;
        $modification->details[$summaryId][$detailId] = array_merge(
            $this->details[$summaryId][$detailId] ?? [
                'value' => $detail->value,
                'included' => $detail->included,
                'discountAmount' => $detail->discountAmount,
            ],
            $changes,
        );

        return $modification;
    }

    private function summary(int $summaryId): PayRunSummary
    {
        return $this->run->summary($summaryId) ?? throw new InvalidArgumentException(sprintf(
            'Pay run %d has no summary %d.',
            $this->run->id,
            $summaryId,
        ));
    }

    private function detail(int $summaryId, int $detailId): PayRunDetail
    {
        return $this->summary($summaryId)->detail($detailId) ?? throw new InvalidArgumentException(sprintf(
            'Summary %d of pay run %d has no detail %d.',
            $summaryId,
            $this->run->id,
            $detailId,
        ));
    }

    private static function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
