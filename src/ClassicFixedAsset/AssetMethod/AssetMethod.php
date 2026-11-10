<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Organisation\CompanyReference;
use SolveBeam\Twinfield\Organisation\Organisation;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * An asset method as Twinfield returns it: how fixed assets are depreciated and on which accounts.
 *
 *   $method = $twinfield->classicAssetMethods()->get('MACHINE');
 *   echo $method->code, ' ', $method->name, ': ', $method->periods, ' periods', PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/asset-methods/
 */
final readonly class AssetMethod
{
    /**
     * @param list<FreeTextField> $freeTexts The five free text fields, by id 1 to 5.
     */
    public function __construct(
        /** Company (office) with its organisation; `$company->code` is the office code. */
        public CompanyReference $company,
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public AssetMethodStatus $status,
        /** True when fixed assets use this method. Deleting it then hides it. */
        public bool $inUse,
        public DateTimeImmutable $created,
        public DateTimeImmutable $modified,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Code of the user who changed the method last. */
        public string $modifiedBy,
        public CalculationMethod $calculationMethod,
        /** Number of periods over which assets are depreciated. */
        public int $periods,
        /** Depreciation percentage per year, e.g. `20` or `12.5`; null when not set (e.g. linear). */
        public ?string $percentage,
        public DepreciationStart $depreciationStart,
        public BalanceAccounts $balanceAccounts,
        public ProfitLossAccounts $profitLossAccounts,
        public array $freeTexts,
    ) {
        $required = ['code' => $code, 'name' => $name, 'modifiedBy' => $modifiedBy];

        foreach ($required as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Asset method %s must not be empty.', $field));
            }
        }

        if ($touched < 0 || $periods < 0) {
            throw new InvalidArgumentException('Asset method touched and periods must be 0 or more.');
        }

        if ($percentage !== null && preg_match('~^\d+(\.\d+)?$~', $percentage) !== 1) {
            throw new InvalidArgumentException(sprintf('Asset method percentage "%s" is not a number.', $percentage));
        }

        $ids = array_map(static fn (FreeTextField $field): int => $field->id, $freeTexts);

        if ($ids !== range(1, FreeTextField::COUNT)) {
            throw new InvalidArgumentException(sprintf(
                'Asset method free texts must be the fields 1 to 5 in order, got: %s.',
                implode(', ', $ids),
            ));
        }
    }

    /**
     * From an `<assetmethod>` element of a read response.
     */
    public static function fromXml(XmlElement $method, Organisation $organisation): self
    {
        $freeTexts = array_map(FreeTextField::fromXml(...), $method->child('freetexts')->list('freetext'));
        $ids = array_map(static fn (FreeTextField $field): int => $field->id, $freeTexts);

        // Twinfield always returns all five fields, also the unused ones.
        if ($ids !== range(1, FreeTextField::COUNT)) {
            throw new UnexpectedResponseException(sprintf(
                '%s/freetexts has the fields %s, expected 1 to 5.',
                $method->path,
                implode(', ', $ids) ?: 'none',
            ));
        }

        $percentage = $method->nullableString('percentage');

        if ($percentage !== null && preg_match('~^\d+(\.\d+)?$~', $percentage) !== 1) {
            throw $method->child('percentage')->invalid('a percentage');
        }

        return new self(
            company: new CompanyReference($organisation, $method->string('office')),
            code: $method->string('code'),
            name: $method->string('name'),
            // Optional in Twinfield: empty when no short name is set.
            shortName: $method->nullableString('shortname'),
            status: $method->enumAttribute('status', AssetMethodStatus::class),
            inUse: $method->boolAttribute('inuse'),
            created: $method->dateTime('created'),
            modified: $method->dateTime('modified'),
            touched: $method->int('touched'),
            modifiedBy: $method->string('user'),
            calculationMethod: $method->enum('calcmethod', CalculationMethod::class),
            periods: $method->int('nrofperiods'),
            // Empty for methods without percentage, such as linear.
            percentage: $percentage,
            depreciationStart: $method->enum('depreciatereconciliation', DepreciationStart::class),
            balanceAccounts: BalanceAccounts::fromXml($method->child('balanceaccounts')),
            profitLossAccounts: ProfitLossAccounts::fromXml($method->child('profitlossaccounts')),
            freeTexts: $freeTexts,
        );
    }

    /**
     * Free text field 1 to 5.
     */
    public function freeText(int $id): FreeTextField
    {
        foreach ($this->freeTexts as $field) {
            if ($field->id === $id) {
                return $field;
            }
        }

        throw new InvalidArgumentException(sprintf('Free text field id must be 1 to 5, %d given.', $id));
    }

    /**
     * Input with all values of this asset method, to change and pass to update() or save().
     *
     *   $assetMethods->update($method->edit()->name('New name'));
     */
    public function edit(): AssetMethodInput
    {
        $balance = $this->balanceAccounts;
        $profitLoss = $this->profitLossAccounts;

        $input = AssetMethodInput::create($this->code, $this->name)
            ->shortName($this->shortName)
            ->depreciateFrom($this->depreciationStart)
            ->purchaseValueGroup($balance->purchaseValueGroup?->code)
            ->purchaseValueAccount($balance->purchaseValue?->code)
            ->depreciationGroup($balance->depreciationGroup?->code)
            ->cumulativeDepreciationAccount($balance->depreciation?->code)
            ->assetsToActivateAccount($balance->assetsToActivate?->code)
            ->revaluationAccount($balance->reconciliation?->code)
            ->toBeInvoicedAccount($balance->toBeInvoiced?->code)
            ->depreciationCostsAccount($profitLoss->depreciation?->code)
            ->disposalResultAccount($profitLoss->sales?->code)
            ->revaluationReserveAccount($profitLoss->reconciliation?->code);

        foreach ($this->freeTexts as $field) {
            $input = $input->freeText($field->id, $field->label, $field->type);
        }

        return match ($this->calculationMethod) {
            CalculationMethod::None => $input->noDepreciation(),
            CalculationMethod::Linear => $input->linear($this->periods),
            CalculationMethod::LinearPercentage => $input->linearPercentage($this->requiredPercentage()),
            CalculationMethod::ReducingBalance => $input->reducingBalance($this->requiredPercentage()),
        };
    }

    private function requiredPercentage(): string
    {
        return $this->percentage ?? throw new UnexpectedResponseException(sprintf(
            'Asset method %s calculates %s without percentage.',
            $this->code,
            $this->calculationMethod->value,
        ));
    }
}
