<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\FixedAsset;

use DateTimeImmutable;
use InvalidArgumentException;
use SolveBeam\Twinfield\Dimension\DimensionBehaviour;
use SolveBeam\Twinfield\Dimension\DimensionStatus;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\Transaction\Amount;
use SolveBeam\Twinfield\Transaction\DimensionReference;
use SolveBeam\Twinfield\Transaction\FinancialPeriod;
use SolveBeam\Twinfield\Transaction\TransactionXml;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * A fixed asset (dimension type `AST`) as Twinfield returns it.
 *
 *   $asset = $twinfield->fixedAssets()->get('60000');
 *
 *   echo $asset->code, ' ', $asset->name, ': ', $asset->assetStatus->name, PHP_EOL;
 *   echo $asset->method?->name, ', ', $asset->periods, ' periods from ', $asset->depreciateFrom, PHP_EOL;
 *
 * To change it, call edit() and pass the result to update().
 *
 * @see https://developers.twinfield.com/documentation/api/master-data/fixed-assets/
 */
final readonly class FixedAsset
{
    /**
     * @param list<string> $groups
     * @param array<int, ?string> $freeTexts Free text 1 to 5 by id; null when empty.
     * @param list<FixedAssetTransactionLine> $transactionLines
     * @param list<FixedAssetField> $lockedFields
     */
    public function __construct(
        /** Company (office) code. */
        public string $company,
        /** Code in the format of the AST dimension type of the company, e.g. `60000`. */
        public string $code,
        /** Unique ID of the dimension, e.g. `a3b86cfc-62ed-44cf-b1fe-bfb1ddb30e37`. */
        public string $uid,
        /** Name, at most 80 characters. */
        public string $name,
        /** Status of the dimension: active, deleted or hidden. See assetStatus for the asset. */
        public DimensionStatus $status,
        /** True when the asset is used in a transaction. Deleting it then hides it. */
        public bool $inUse,
        public DimensionBehaviour $behaviour,
        /** Number of times the settings were changed. */
        public int $touched,
        /** Last change. Null when Twinfield leaves it out: it is not in every documented response. */
        public ?DateTimeImmutable $modified,
        /** Cost center (dimension 2) of the asset, e.g. `00000`. */
        public DimensionReference $costCenter,
        /** Default VAT code; null when not set. */
        public ?string $vatCode,
        /** Dimension group codes. */
        public array $groups,
        /** Where the asset is in its life: to be activated, active, inactive or sold. */
        public FixedAssetStatus $assetStatus,
        /** Null when no asset method is set. */
        public ?AssetMethodReference $method,
        /** Number of periods over which the asset is depreciated; null when empty. */
        public ?int $periods,
        /** True when the number of periods comes from the asset method (`nrofperiods/@inherited`). */
        public bool $periodsInherited,
        /**
         * Depreciation percentage per year, e.g. `20` or `12.5`; null when not set (e.g. linear).
         *
         * @var ?numeric-string
         */
        public ?string $percentage,
        /** Value at the end of the depreciation. */
        public Amount $residualValue,
        /** Book value at which depreciation stops. */
        public Amount $stopValue,
        /** First period of depreciation (`beginperiod`); null when not set yet. */
        public ?FinancialPeriod $depreciateFrom,
        /** Period of the last depreciation; null when the asset was not depreciated yet. */
        public ?FinancialPeriod $lastDepreciation,
        /** Null when not set. */
        public ?DateTimeImmutable $purchaseDate,
        /** Null when the asset is not sold. */
        public ?DateTimeImmutable $sellDate,
        public array $freeTexts,
        /** The purchase and later transactions on the asset; empty until one is booked. */
        public array $transactionLines,
        /** Settings Twinfield does not allow to change now, e.g. the begin period of an active asset. */
        public array $lockedFields,
    ) {
        foreach (['company' => $company, 'code' => $code, 'uid' => $uid, 'name' => $name] as $field => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Fixed asset %s must not be empty.', $field));
            }
        }

        if ($touched < 0) {
            throw new InvalidArgumentException('Fixed asset touched count must be 0 or more.');
        }

        if ($periods !== null && $periods < 1) {
            throw new InvalidArgumentException(sprintf('Fixed asset periods must be 1 or more, %d given.', $periods));
        }

        if ($percentage !== null && preg_match('~^\d+(\.\d+)?$~', $percentage) !== 1) {
            throw new InvalidArgumentException(sprintf('Fixed asset percentage "%s" is not a number.', $percentage));
        }

        if (array_keys($freeTexts) !== range(1, 5)) {
            throw new InvalidArgumentException('Fixed asset free texts must be the fields 1 to 5 in order.');
        }

        foreach ($freeTexts as $id => $freeText) {
            if ($freeText === '') {
                throw new InvalidArgumentException(
                    sprintf('Fixed asset free text %d must not be empty: use null.', $id),
                );
            }
        }
    }

    /**
     * From a `<dimension>` element of a read or save response.
     */
    public static function fromXml(XmlElement $dimension): self
    {
        $type = $dimension->string('type');

        if ($type !== 'AST') {
            throw $dimension->child('type')->invalid('AST');
        }

        $financials = $dimension->child('financials');
        $fixedAssets = $dimension->child('fixedassets');

        $lockedFields = array_values(array_filter(
            FixedAssetField::cases(),
            static function (FixedAssetField $field) use ($fixedAssets): bool {
                $element = $fixedAssets->child($field->value);

                // Read responses have @locked on every element. The documented save response
                // leaves it out on the elements of the request, which are not locked then.
                return $element->element->hasAttribute('locked') && $element->boolAttribute('locked');
            },
        ));

        $freeTexts = [];

        for ($id = 1; $id <= 5; $id++) {
            // Optional in Twinfield: empty when the field is not used.
            $freeTexts[$id] = $fixedAssets->nullableString('freetext' . $id);
        }

        $percentage = $fixedAssets->nullableString('percentage');

        if ($percentage !== null && (preg_match('~^\d+(\.\d+)?$~', $percentage) !== 1 || !is_numeric($percentage))) {
            throw $fixedAssets->child('percentage')->invalid('a percentage');
        }

        $periods = $fixedAssets->child('nrofperiods');

        return new self(
            company: $dimension->string('office'),
            code: $dimension->string('code'),
            uid: $dimension->string('uid'),
            name: $dimension->string('name'),
            status: $dimension->enumAttribute('status', DimensionStatus::class),
            inUse: $dimension->bool('inuse'),
            behaviour: $dimension->enum('behaviour', DimensionBehaviour::class),
            touched: $dimension->int('touched'),
            // Not in the save response of the documentation, but in every read response.
            modified: $dimension->nullableChild('modified') === null ? null : $dimension->dateTime('modified'),
            costCenter: self::costCenter($financials),
            // Optional in Twinfield: <vatcode fixed="false"/> when not set.
            vatCode: $financials->nullableString('vatcode'),
            // Twinfield leaves out <groups> when the asset is in no group.
            groups: array_map(
                static fn (XmlElement $group): string => $group->text() !== ''
                    ? $group->text()
                    : throw $group->invalid('a group code'),
                $dimension->nullableChild('groups')?->list('group') ?? [],
            ),
            assetStatus: $fixedAssets->enum('status', FixedAssetStatus::class),
            // Empty in live responses for assets without method; the periods are then empty too.
            method: AssetMethodReference::fromXml($fixedAssets->child('method')),
            periods: $periods->text() === '' ? null : self::positive($fixedAssets, 'nrofperiods'),
            periodsInherited: $periods->boolAttribute('inherited'),
            // Empty for asset methods without percentage, such as linear.
            percentage: $percentage,
            residualValue: TransactionXml::amount($fixedAssets, 'residualvalue'),
            stopValue: TransactionXml::amount($fixedAssets, 'stopvalue'),
            // The periods and dates below are empty until they are set or happen.
            depreciateFrom: self::nullablePeriod($fixedAssets, 'beginperiod'),
            lastDepreciation: self::nullablePeriod($fixedAssets, 'lastdepreciation'),
            purchaseDate: $fixedAssets->nullableDate('purchasedate'),
            sellDate: $fixedAssets->nullableDate('selldate'),
            freeTexts: $freeTexts,
            transactionLines: array_map(
                FixedAssetTransactionLine::fromXml(...),
                $fixedAssets->child('translines')->list('transline'),
            ),
            lockedFields: $lockedFields,
        );
    }

    /**
     * Free text field 1 to 5; null when empty. The asset method has the label of each field.
     */
    public function freeText(int $id): ?string
    {
        if ($id < 1 || $id > 5) {
            throw new InvalidArgumentException(sprintf('Free text field id must be 1 to 5, %d given.', $id));
        }

        return $this->freeTexts[$id];
    }

    /**
     * True when Twinfield does not allow changing this setting now.
     */
    public function isLocked(FixedAssetField $field): bool
    {
        return in_array($field, $this->lockedFields, true);
    }

    /**
     * Input with the values of this asset that may be changed, to change and pass to update()
     * or save().
     *
     *   $fixedAssets->update($asset->edit()->residualValue('1000.00'));
     *
     * Locked settings are left out, and setting one throws a LogicException. The number of
     * periods is left out when it comes from the asset method, so it keeps following the method.
     */
    public function edit(): FixedAssetInput
    {
        $input = FixedAssetInput::create($this->name)
            ->code($this->code)
            ->costCenter($this->costCenter->code)
            ->vatCode($this->vatCode)
            ->residualValue($this->residualValue)
            ->stopValue($this->stopValue)
            ->depreciateFrom($this->depreciateFrom)
            ->purchaseDate($this->purchaseDate)
            ->percentage($this->percentage)
            ->lastDepreciation($this->lastDepreciation);

        if ($this->method !== null) {
            $input = $input->method($this->method->code);
        }

        if (!$this->periodsInherited && $this->periods !== null) {
            $input = $input->periods($this->periods);
        }

        foreach ($this->freeTexts as $id => $freeText) {
            $input = $input->freeText($id, $freeText);
        }

        // Twinfield leaves out <groups> when it is empty, so the groups are only sent when set.
        if ($this->groups !== []) {
            $input = $input->groups(...$this->groups);
        }

        // Leaves the locked settings out again.
        return $input->withLockedFields(...$this->lockedFields);
    }

    /**
     * The cost center from `<substitutionlevel>2</substitutionlevel><substitutewith …>00000</substitutewith>`.
     */
    private static function costCenter(XmlElement $financials): DimensionReference
    {
        $level = $financials->int('substitutionlevel');

        if ($level !== 2) {
            throw $financials->child('substitutionlevel')->invalid('2 (the cost center level)');
        }

        $substitute = $financials->child('substitutewith');
        $code = $financials->string('substitutewith');
        // Live responses have both attributes; the documented response has no dimensiontype.
        $name = $substitute->element->getAttribute('name');
        $dimensionType = $substitute->element->getAttribute('dimensiontype');

        return new DimensionReference(
            $code,
            $dimensionType === '' ? null : $dimensionType,
            $name === '' ? null : $name,
        );
    }

    private static function nullablePeriod(XmlElement $fixedAssets, string $name): ?FinancialPeriod
    {
        return $fixedAssets->child($name)->text() === '' ? null : TransactionXml::period($fixedAssets, $name);
    }

    private static function positive(XmlElement $parent, string $name): int
    {
        $value = $parent->int($name);

        if ($value < 1) {
            throw new UnexpectedResponseException(
                sprintf('%s/%s is %d, expected 1 or more.', $parent->path, $name, $value),
            );
        }

        return $value;
    }
}
