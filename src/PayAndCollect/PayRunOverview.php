<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\PayAndCollect;

use DateTimeImmutable;
use SolveBeam\Twinfield\CommandQuery\ResultElement;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A pay run in the list of PayAndCollectResource::list() (`PayOverview`), without its summaries.
 * Read the summaries with get().
 */
final readonly class PayRunOverview
{
    /**
     * @param array<string, string> $customSettings Settings of the pay type, by name.
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
        public FinancialPeriod $period,
        /** Whether excluded invoices are available again right after posting (`DirectRemove`). */
        public bool $directRemove,
        public array $customSettings,
    ) {
    }

    /**
     * @internal
     */
    public static function fromResult(ResultElement $run, CompanyReference $company): self
    {
        self::assertCompany($run, $company);

        return new self(
            company: $company,
            payType: PayAndCollectXml::string($run, 'PayType'),
            id: $run->int('Id'),
            created: $run->dateTime('Created'),
            version: PayAndCollectXml::version($run, 'Touched', 'Modified'),
            user: PayAndCollectXml::string($run, 'User'),
            runType: $run->enum('RunType', PayRunType::class),
            phase: $run->enum('Phase', PayRunPhase::class),
            status: $run->enum('Status', PayRunStatus::class),
            bank: PayAndCollectXml::string($run, 'Bank'),
            payDate: PayAndCollectXml::date($run, 'PayDate'),
            period: FinancialPeriod::of($run->int('Year'), $run->int('Period')),
            directRemove: $run->bool('DirectRemove'),
            customSettings: PayAndCollectXml::settings($run, 'CustomSettings'),
        );
    }

    /**
     * @internal
     */
    public static function assertCompany(ResultElement $run, CompanyReference $company): void
    {
        $office = PayAndCollectXml::string($run, 'Office');

        if ($office !== $company->code) {
            throw new UnexpectedResponseException(sprintf(
                '%s/Office is "%s", expected the company of the request "%s".',
                $run->path,
                $office,
                $company->code,
            ));
        }
    }
}
