<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

use DOMElement;
use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;

/**
 * A general ledger account to create or update. Immutable: every method returns a new input.
 *
 *   $input = GeneralLedgerAccountInput::profitAndLoss('8010', 'Revenue services')
 *       ->shortName('Services')
 *       ->vatCode('VH')
 *       ->subAnalysis(SubAnalysis::Required)
 *       ->childValidation(ChildValidation::dimensionType('KPL'))
 *       ->group('REVENUE');
 *
 *   $input = GeneralLedgerAccountInput::balanceSheet('2050', 'Suspense account')->matchable();
 *
 * The input knows its type (BAS or PNL): the resource of the other type refuses it, so an
 * account never ends up as the wrong type.
 *
 * Only what you set is sent: anything else gets the default of the dimension type when
 * creating, and stays as it is when updating. Setting a value to null clears it. For an
 * existing account, start from `$account->edit()`, which sets every value.
 *
 * Child validations and groups are sent as a whole once you set them: one you leave out is
 * removed.
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/general-ledger-accounts/
 */
final readonly class GeneralLedgerAccountInput
{
    /**
     * Elements in the order of the documentation; `a/b` is element `b` in `a`.
     */
    private const ELEMENTS = [
        'shortname',
        'beginperiod',
        'beginyear',
        'endperiod',
        'endyear',
        'financials/matchtype',
        'financials/subanalyse',
        'financials/vatcode',
    ];

    /**
     * @param array<string, ?string> $values Element (see ELEMENTS) => value; null sends an
     *     empty element, which clears the value.
     * @param ?list<ChildValidation> $childValidations Null when not set: they are not sent.
     * @param ?list<string> $groups Null when not set: the groups are not sent.
     */
    private function __construct(
        public GeneralLedgerAccountType $type,
        public string $code,
        public string $name,
        private array $values,
        /** `vatcode/@fixed`; only sent with the VAT code. */
        private bool $vatCodeFixed,
        public ?array $childValidations,
        public ?array $groups,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('General ledger account name must not be empty.');
        }

        XmlValue::maxLength('general ledger account name', $name, 80);
    }

    /**
     * A balance sheet account (dimension type `BAS`).
     *
     * @param string $code Code in the format of the BAS dimension type of the company, e.g. `1310`.
     * @param string $name Name, at most 80 characters.
     */
    public static function balanceSheet(string $code, string $name): self
    {
        return self::of(GeneralLedgerAccountType::BalanceSheet, $code, $name);
    }

    /**
     * A profit and loss account (dimension type `PNL`).
     *
     * @param string $code Code in the format of the PNL dimension type of the company, e.g. `8010`.
     * @param string $name Name, at most 80 characters.
     */
    public static function profitAndLoss(string $code, string $name): self
    {
        return self::of(GeneralLedgerAccountType::ProfitAndLoss, $code, $name);
    }

    /**
     * An account of the given type, e.g. when the type is a variable.
     */
    public static function of(GeneralLedgerAccountType $type, string $code, string $name): self
    {
        return new self($type, $code, $name, [], false, null, null);
    }

    /**
     * Name, at most 80 characters.
     */
    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Short name, at most 20 characters; null clears it.
     */
    public function shortName(?string $shortName): self
    {
        $shortName = $shortName === null || $shortName === ''
            ? null
            : XmlValue::maxLength('general ledger account short name', $shortName, 20);

        return $this->set('shortname', $shortName);
    }

    /**
     * First period the account can be used in; null for no limit.
     */
    public function activeFrom(?FinancialPeriod $period): self
    {
        return $this->setMany([
            'beginperiod' => (string) ($period->number ?? 0),
            'beginyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Last period the account can be used in; null for no limit.
     */
    public function activeUntil(?FinancialPeriod $period): self
    {
        return $this->setMany([
            'endperiod' => (string) ($period->number ?? 0),
            'endyear' => (string) ($period->year ?? 0),
        ]);
    }

    /**
     * Whether transaction lines on the account can be matched, e.g. for a suspense account.
     */
    public function matchable(bool $matchable = true): self
    {
        return $this->set('financials/matchtype', $matchable ? 'matchable' : 'notmatchable');
    }

    /**
     * Whether lines need a dimension 2, e.g. a cost center. Say which with childValidation().
     */
    public function subAnalysis(SubAnalysis $subAnalysis): self
    {
        return $this->set('financials/subanalyse', $subAnalysis->value);
    }

    /**
     * Default VAT code, e.g. `VH`; null clears it. With `fixed: true`, lines on the account can
     * only use this VAT code.
     */
    public function vatCode(?string $vatCode, bool $fixed = false): self
    {
        $vatCode = $vatCode === '' ? null : $vatCode;

        if ($vatCode === null && $fixed) {
            throw new InvalidArgumentException('A fixed VAT code needs a VAT code.');
        }

        return $this->with([
            'values' => [...$this->values, 'financials/vatcode' => $vatCode === null ? null : XmlValue::vatCode($vatCode)],
            'vatCodeFixed' => $fixed,
        ]);
    }

    /**
     * Adds a child validation, e.g. `ChildValidation::dimensionType('KPL')`.
     */
    public function childValidation(ChildValidation $validation): self
    {
        return $this->childValidations(...[...$this->childValidations ?? [], $validation]);
    }

    /**
     * Replaces all child validations; `childValidations()` without arguments removes them.
     */
    public function childValidations(ChildValidation ...$validations): self
    {
        $unique = [];

        foreach ($validations as $validation) {
            // The same validation twice says nothing more.
            $unique[$validation->type->value . '|' . $validation->level . '|' . $validation->value] = $validation;
        }

        return $this->with(['childValidations' => array_values($unique)]);
    }

    /**
     * Adds the account to a dimension group, e.g. `REVENUE`.
     */
    public function group(string $group): self
    {
        return $this->groups(...[...$this->groups ?? [], $group]);
    }

    /**
     * Replaces all dimension groups; `groups()` without arguments removes them.
     */
    public function groups(string ...$groups): self
    {
        foreach ($groups as $group) {
            XmlValue::code($group);
        }

        return $this->with(['groups' => array_values(array_unique($groups))]);
    }

    /**
     * The value that will be sent for an element, e.g. `value('financials/subanalyse')`, or
     * null when it is empty or not set. See has() to tell these apart.
     */
    public function value(string $element): ?string
    {
        return $this->values[$element] ?? null;
    }

    /**
     * True when the element will be sent, e.g. `has('financials/vatcode')`.
     */
    public function has(string $element): bool
    {
        return array_key_exists($element, $this->values);
    }

    /**
     * The `<dimension>` request to save this account in a company.
     *
     * `financials/accounttype` is not sent: it is read-only and follows from the type.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('dimension');
        $dimension = $xml->root();

        $xml->add($dimension, 'office', $company);
        $xml->add($dimension, 'type', $this->type->value);
        $xml->add($dimension, 'code', $this->code);
        $xml->add($dimension, 'name', $this->name);

        $financials = null;

        foreach (self::ELEMENTS as $path) {
            if (!array_key_exists($path, $this->values)) {
                continue;
            }

            $value = $this->values[$path];

            if (!str_starts_with($path, 'financials/')) {
                $xml->add($dimension, $path, $value);

                continue;
            }

            $financials ??= $xml->add($dimension, 'financials');
            $name = substr($path, strlen('financials/'));
            $attributes = $name === 'vatcode' ? ['fixed' => XmlValue::boolean($this->vatCodeFixed)] : [];

            $xml->add($financials, $name, $value, $attributes);
        }

        if ($this->childValidations !== null) {
            $financials ??= $xml->add($dimension, 'financials');
            $this->appendChildValidations($xml, $financials, $this->childValidations);
        }

        if ($this->groups !== null) {
            $element = $xml->add($dimension, 'groups');

            foreach ($this->groups as $group) {
                $xml->add($element, 'group', $group);
            }
        }

        return $xml;
    }

    /**
     * @param list<ChildValidation> $validations
     */
    private function appendChildValidations(XmlBuilder $xml, DOMElement $financials, array $validations): void
    {
        $element = $xml->add($financials, 'childvalidations');

        foreach ($validations as $validation) {
            $xml->add($element, 'childvalidation', $validation->value, [
                'type' => $validation->type->value,
                'level' => (string) $validation->level,
            ]);
        }
    }

    private function set(string $element, ?string $value): self
    {
        return $this->setMany([$element => $value]);
    }

    /**
     * @param array<string, ?string> $values
     */
    private function setMany(array $values): self
    {
        return $this->with(['values' => [...$this->values, ...$values]]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}
