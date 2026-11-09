<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\VatCode\VatCodeReference;

/**
 * A general ledger account as Twinfield returns it: a balance sheet account (dimension type
 * `BAS`) or a profit and loss account (`PNL`).
 *
 *   $account = $twinfield->profitAndLossAccounts()->get('8000');
 *
 *   echo $account->code, ' ', $account->name, PHP_EOL;
 *   echo $account->subAnalysis->name, PHP_EOL;     // Optional
 *   echo $account->vatCode?->code, PHP_EOL;        // VH
 *
 * To change it, call edit() and pass the result to update().
 *
 * Twinfield documents the other elements of the response (due days, pay code, e-invoicing,
 * collect mandate, website, …) as "not in use" for general ledger accounts, so they are left out.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/general-ledger-accounts/
 */
final readonly class GeneralLedgerAccount
{
    /**
     * @param list<ChildValidation> $childValidations
     * @param list<string> $groups
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        public GeneralLedgerAccountType $type,
        /** Code in the format of the BAS or PNL dimension type of the company, e.g. `8000`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `dffe0708-3b77-40b6-ad1a-41cbbf4d77d6`. */
        public string $uid,
        /** Name, at most 80 characters. */
        public string $name,
        /** Short name, at most 20 characters; null when not set. */
        public ?string $shortName,
        public DimensionStatus $status,
        /**
         * True when the account is used in a transaction, or linked to a VAT code or article.
         * Deleting it then hides it.
         */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
        /** First period the account can be used in; null for no limit. */
        public ?FinancialPeriod $activeFrom,
        /** Last period the account can be used in; null for no limit. */
        public ?FinancialPeriod $activeUntil,
        /** True when transaction lines on the account can be matched, e.g. a suspense account. */
        public bool $matchable,
        public SubAnalysis $subAnalysis,
        /** Default VAT code; null when not set. */
        public ?VatCodeReference $vatCode,
        /** True when lines on the account can only use the default VAT code. */
        public bool $vatCodeFixed,
        /** Which dimensions lines may have below the account, e.g. only cost centers. */
        public array $childValidations,
        /** Dimension group codes. */
        public array $groups,
    ) {
        foreach (['code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('General ledger account %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('General ledger account touched count must be 0 or more.');
        }

        if ($vatCodeFixed && $vatCode === null) {
            throw new InvalidArgumentException(
                'A general ledger account without VAT code cannot have a fixed VAT code.',
            );
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     */
    public static function fromXml(XmlElement $dimension, Organisation $organisation): self
    {
        $type = $dimension->enum('type', GeneralLedgerAccountType::class);
        $financials = $dimension->child('financials');

        if ($financials->string('accounttype') !== $type->accountType()) {
            throw $financials->child('accounttype')->invalid(
                sprintf('%s for a %s', $type->accountType(), $type->label()),
            );
        }

        $vatCode = $financials->child('vatcode');

        return new self(
            company: new CompanyReference($organisation, $dimension->string('office')),
            type: $type,
            code: $dimension->string('code'),
            uid: $dimension->string('uid'),
            name: $dimension->string('name'),
            // Optional in Twinfield: empty when no short name is set.
            shortName: $dimension->nullableString('shortname'),
            status: $dimension->enumAttribute('status', DimensionStatus::class),
            inUse: $dimension->bool('inuse'),
            behaviour: $dimension->enum('behaviour', DimensionBehaviour::class),
            touched: $dimension->int('touched'),
            // Not in the save response of the documentation, but in every read response.
            modified: $dimension->nullableChild('modified') === null ? null : $dimension->dateTime('modified'),
            activeFrom: self::period($dimension, 'beginyear', 'beginperiod'),
            activeUntil: self::period($dimension, 'endyear', 'endperiod'),
            matchable: match ($financials->string('matchtype')) {
                'matchable' => true,
                'notmatchable' => false,
                default => throw $financials->child('matchtype')->invalid('matchable or notmatchable'),
            },
            subAnalysis: $financials->enum('subanalyse', SubAnalysis::class),
            vatCode: VatCodeReference::fromXml($vatCode),
            vatCodeFixed: $vatCode->boolAttribute('fixed'),
            // Twinfield leaves out <childvalidations> and <groups> when they are empty.
            childValidations: array_map(
                ChildValidation::fromXml(...),
                $financials->nullableChild('childvalidations')?->list('childvalidation') ?? [],
            ),
            groups: array_map(
                static fn (XmlElement $group): string => $group->text() !== ''
                    ? $group->text()
                    : throw $group->invalid('a group code'),
                $dimension->nullableChild('groups')?->list('group') ?? [],
            ),
        );
    }

    public function isBalanceSheet(): bool
    {
        return $this->type === GeneralLedgerAccountType::BalanceSheet;
    }

    public function isProfitAndLoss(): bool
    {
        return $this->type === GeneralLedgerAccountType::ProfitAndLoss;
    }

    /**
     * True when the account is in this dimension group.
     */
    public function inGroup(string $group): bool
    {
        return in_array($group, $this->groups, true);
    }

    /**
     * Input with all values of this account, to change and pass to update() or save().
     *
     *   $accounts->update($account->edit()->name('Revenue consultancy'));
     *
     * It carries the child validations and groups, so an update keeps what you do not change.
     */
    public function edit(): GeneralLedgerAccountInput
    {
        $input = GeneralLedgerAccountInput::of($this->type, $this->code, $this->name)
            ->shortName($this->shortName)
            ->activeFrom($this->activeFrom)
            ->activeUntil($this->activeUntil)
            ->matchable($this->matchable)
            ->subAnalysis($this->subAnalysis)
            ->vatCode($this->vatCode?->code, $this->vatCodeFixed);

        // Twinfield leaves these out when they are empty, so they are only sent when set.
        if ($this->childValidations !== []) {
            $input = $input->childValidations(...$this->childValidations);
        }

        if ($this->groups !== []) {
            $input = $input->groups(...$this->groups);
        }

        return $input;
    }

    /**
     * A period from a year and period element; Twinfield uses 0 for "no limit".
     */
    private static function period(XmlElement $dimension, string $yearElement, string $periodElement): ?FinancialPeriod
    {
        $year = $dimension->int($yearElement);
        $period = $dimension->int($periodElement);

        return $year === 0 ? null : FinancialPeriod::of($year, $period);
    }
}
