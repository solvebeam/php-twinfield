<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\AssetMethod;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlBuilder;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * An asset method to create or update. Immutable: every method returns a new input.
 *
 *   $input = AssetMethodInput::create('MACHINE', 'Machine')
 *       ->linear(60)
 *       ->depreciateFrom(DepreciationStart::FromPurchaseDate)
 *       ->purchaseValueAccount('0501')
 *       ->cumulativeDepreciationAccount('0135')
 *       ->depreciationCostsAccount('4730')
 *       ->freeText(1, 'Serial number');
 *
 * For an existing asset method, start from `$method->edit()`, so all current values are kept.
 */
final readonly class AssetMethodInput
{
    /** Account elements per section, in the order of the documentation. */
    private const array ACCOUNTS = [
        'balanceaccounts' => [
            'purchasevaluegroup',
            'purchasevalue',
            'depreciationgroup',
            'depreciation',
            'assetstoactivate',
            'reconciliation',
            'tobeinvoiced',
        ],
        'profitlossaccounts' => ['depreciation', 'sales', 'reconciliation'],
    ];

    /**
     * @param array<string, string> $accounts Account codes by `section/element`; left out when not set.
     * @param array<int, FreeTextField> $freeTexts Free text fields by id; left out when not used.
     */
    private function __construct(
        public string $code,
        public string $name,
        public ?string $shortName,
        public ?CalculationMethod $calculationMethod,
        public ?int $periods,
        public ?string $percentage,
        public ?DepreciationStart $depreciationStart,
        private array $accounts,
        private array $freeTexts,
    ) {
        XmlValue::code($code);

        if ($name === '') {
            throw new InvalidArgumentException('Asset method name must not be empty.');
        }
    }

    /**
     * @param string $code Asset method code, e.g. `MACHINE`.
     */
    public static function create(string $code, string $name): self
    {
        return new self($code, $name, null, null, null, null, null, [], []);
    }

    public function name(string $name): self
    {
        return $this->with(['name' => $name]);
    }

    /**
     * Null removes it.
     */
    public function shortName(?string $shortName): self
    {
        return $this->with(['shortName' => $shortName === '' ? null : $shortName]);
    }

    /**
     * Depreciate the same amount every period, over this number of periods, e.g. `linear(60)`.
     */
    public function linear(int $periods): self
    {
        if ($periods < 1) {
            throw new InvalidArgumentException(sprintf('Number of periods must be 1 or more, %d given.', $periods));
        }

        return $this->with([
            'calculationMethod' => CalculationMethod::Linear,
            'periods' => $periods,
            'percentage' => null,
        ]);
    }

    /**
     * Depreciate the same amount every period: this percentage of the purchase value per year,
     * e.g. `linearPercentage('20')`.
     */
    public function linearPercentage(string $percentage): self
    {
        return $this->withPercentage(CalculationMethod::LinearPercentage, $percentage);
    }

    /**
     * Depreciate this percentage of the remaining book value per year, e.g. `reducingBalance('25')`.
     */
    public function reducingBalance(string $percentage): self
    {
        return $this->withPercentage(CalculationMethod::ReducingBalance, $percentage);
    }

    /**
     * Do not depreciate, e.g. for land.
     */
    public function noDepreciation(): self
    {
        return $this->with(['calculationMethod' => CalculationMethod::None, 'periods' => null, 'percentage' => null]);
    }

    public function depreciateFrom(DepreciationStart $start): self
    {
        return $this->with(['depreciationStart' => $start]);
    }

    /**
     * Dimension group of the purchase value accounts; null removes it.
     */
    public function purchaseValueGroup(?string $group): self
    {
        return $this->withAccount('balanceaccounts/purchasevaluegroup', $group);
    }

    /**
     * Purchase value balance sheet account; null removes it.
     */
    public function purchaseValueAccount(?string $account): self
    {
        return $this->withAccount('balanceaccounts/purchasevalue', $account);
    }

    /**
     * Dimension group of the depreciation accounts; null removes it.
     */
    public function depreciationGroup(?string $group): self
    {
        return $this->withAccount('balanceaccounts/depreciationgroup', $group);
    }

    /**
     * Cumulative depreciation balance sheet account; null removes it.
     */
    public function cumulativeDepreciationAccount(?string $account): self
    {
        return $this->withAccount('balanceaccounts/depreciation', $account);
    }

    /**
     * Assets to activate balance sheet account; null removes it.
     */
    public function assetsToActivateAccount(?string $account): self
    {
        return $this->withAccount('balanceaccounts/assetstoactivate', $account);
    }

    /**
     * Revaluation of purchase value balance sheet account; null removes it.
     */
    public function revaluationAccount(?string $account): self
    {
        return $this->withAccount('balanceaccounts/reconciliation', $account);
    }

    /**
     * Disposal value to be invoiced balance sheet account; null removes it.
     */
    public function toBeInvoicedAccount(?string $account): self
    {
        return $this->withAccount('balanceaccounts/tobeinvoiced', $account);
    }

    /**
     * Depreciation costs profit and loss account; null removes it.
     */
    public function depreciationCostsAccount(?string $account): self
    {
        return $this->withAccount('profitlossaccounts/depreciation', $account);
    }

    /**
     * Disposal result profit and loss account; null removes it.
     */
    public function disposalResultAccount(?string $account): self
    {
        return $this->withAccount('profitlossaccounts/sales', $account);
    }

    /**
     * Revaluation reserve profit and loss account; null removes it.
     */
    public function revaluationReserveAccount(?string $account): self
    {
        return $this->withAccount('profitlossaccounts/reconciliation', $account);
    }

    /**
     * Label and type of free text field 1 to 5 of the fixed assets, e.g. `freeText(1, 'Serial number')`.
     * A null label clears the field.
     */
    public function freeText(int $id, ?string $label, FreeTextType $type = FreeTextType::Text): self
    {
        $freeTexts = $this->freeTexts;
        $freeTexts[$id] = new FreeTextField($id, $type, $label === '' ? null : $label);

        return $this->with(['freeTexts' => $freeTexts]);
    }

    /**
     * The `<assetmethod>` request to save this asset method in a company.
     *
     * Accounts and free texts are always sent, so unset ones are cleared. The calculation
     * settings are only sent when set; Twinfield then keeps the current values.
     */
    public function toXml(string $company): XmlBuilder
    {
        $xml = XmlBuilder::create('assetmethod');
        $method = $xml->root();

        $xml->add($method, 'office', $company);
        $xml->add($method, 'code', $this->code);
        $xml->add($method, 'name', $this->name);
        $xml->add($method, 'shortname', $this->shortName);

        if ($this->calculationMethod !== null) {
            $xml->add($method, 'calcmethod', $this->calculationMethod->value);
        }

        if ($this->periods !== null) {
            $xml->add($method, 'nrofperiods', (string) $this->periods);
        }

        if ($this->percentage !== null) {
            $xml->add($method, 'percentage', $this->percentage);
        }

        if ($this->depreciationStart !== null) {
            $xml->add($method, 'depreciatereconciliation', $this->depreciationStart->value);
        }

        foreach (self::ACCOUNTS as $section => $elements) {
            $parent = $xml->add($method, $section);

            foreach ($elements as $element) {
                $xml->add($parent, $element, $this->accounts[$section . '/' . $element] ?? null);
            }
        }

        $freeTexts = $xml->add($method, 'freetexts');

        foreach (range(1, FreeTextField::COUNT) as $id) {
            // A field that is not set is sent as an unused text field.
            $field = $this->freeTexts[$id] ?? new FreeTextField($id, FreeTextType::Text, null);
            $xml->add($freeTexts, 'freetext', $field->label, ['id' => (string) $id, 'type' => $field->type->value]);
        }

        return $xml;
    }

    private function withPercentage(CalculationMethod $method, string $percentage): self
    {
        $isPercentage = preg_match('~^\d{1,3}(\.\d+)?$~', $percentage) === 1
            && (float) $percentage > 0
            && (float) $percentage <= 100;

        if (!$isPercentage) {
            throw new InvalidArgumentException(sprintf(
                'Percentage must be a number above 0 and at most 100 with a dot as decimal separator, "%s" given.',
                $percentage,
            ));
        }

        return $this->with(['calculationMethod' => $method, 'periods' => null, 'percentage' => $percentage]);
    }

    private function withAccount(string $path, ?string $code): self
    {
        $accounts = $this->accounts;

        if ($code === null || $code === '') {
            unset($accounts[$path]);
        } else {
            $accounts[$path] = XmlValue::code($code);
        }

        return $this->with(['accounts' => $accounts]);
    }

    /**
     * @param array{
     *     name?: string,
     *     shortName?: ?string,
     *     calculationMethod?: CalculationMethod,
     *     periods?: ?int,
     *     percentage?: ?string,
     *     depreciationStart?: DepreciationStart,
     *     accounts?: array<string, string>,
     *     freeTexts?: array<int, FreeTextField>,
     * } $changes
     */
    private function with(array $changes): self
    {
        return new self(
            code: $this->code,
            name: $changes['name'] ?? $this->name,
            shortName: array_key_exists('shortName', $changes) ? $changes['shortName'] : $this->shortName,
            calculationMethod: $changes['calculationMethod'] ?? $this->calculationMethod,
            periods: array_key_exists('periods', $changes) ? $changes['periods'] : $this->periods,
            percentage: array_key_exists('percentage', $changes) ? $changes['percentage'] : $this->percentage,
            depreciationStart: $changes['depreciationStart'] ?? $this->depreciationStart,
            accounts: $changes['accounts'] ?? $this->accounts,
            freeTexts: $changes['freeTexts'] ?? $this->freeTexts,
        );
    }
}
