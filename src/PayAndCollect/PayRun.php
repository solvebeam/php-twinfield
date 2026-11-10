<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Period\Period;

/**
 * A pay run with its summaries (`PayRun`), from PayAndCollectResource::get() or create().
 *
 *   foreach ($run->summaries as $summary) {
 *       echo $summary->relation, ' ', $summary->relationName, ' ', $summary->value(), PHP_EOL;
 *   }
 */
final readonly class PayRun
{
    /**
     * @param array<string, string> $customSettings Settings of the pay type, by name.
     * @param list<PayRunSummary> $summaries
     */
    public function __construct(
        public CompanyReference $company,
        /** Code of the pay type, e.g. `SEPANLCT`. */
        public string $payType,
        /** Number of the run, unique within the company. */
        public int $id,
        public DateTimeImmutable $created,
        /** Version to change the run with; its `modified` is the date and time of the last change. */
        public PayRunVersion $version,
        /** Code of the user who made the last change. */
        public string $user,
        public PayRunType $runType,
        public PayRunPhase $phase,
        public PayRunStatus $status,
        /** Code of the bank (day book), e.g. `ING`. */
        public string $bank,
        public DateTimeImmutable $payDate,
        /** Year and period the payments are posted in. */
        public Period $period,
        /** Whether excluded invoices are available again right after posting (`DirectRemove`). */
        public bool $directRemove,
        public array $customSettings,
        public array $summaries,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $run, CompanyReference $company): self
    {
        $overview = PayRunOverview::fromResult($run, $company);

        return new self(
            company: $overview->company,
            payType: $overview->payType,
            id: $overview->id,
            created: $overview->created,
            version: $overview->version,
            user: $overview->user,
            runType: $overview->runType,
            phase: $overview->phase,
            status: $overview->status,
            bank: $overview->bank,
            payDate: $overview->payDate,
            period: $overview->period,
            directRemove: $overview->directRemove,
            customSettings: $overview->customSettings,
            // Left out when the run has no summaries, e.g. while it is created asynchronously.
            summaries: $run->nullableChild('Summaries') === null
                ? []
                : array_map(PayRunSummary::fromResult(...), $run->list('Summaries', 'SummaryInfo')),
        );
    }

    public function summary(int $id): ?PayRunSummary
    {
        foreach ($this->summaries as $summary) {
            if ($summary->id === $id) {
                return $summary;
            }
        }

        return null;
    }
}
