<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\GeneralLedgerAccount;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\ProcessXml\XmlValue;

/**
 * Which dimensions transaction lines on an account may have below it, e.g. only cost centers
 * (`KPL`) as dimension 2 (`<childvalidation type="type" level="2">KPL</childvalidation>`).
 *
 *   ChildValidation::dimensionType('KPL');      // any cost center, level 2
 *   ChildValidation::dimension('00100', 2);     // only cost center 00100
 *   ChildValidation::group('MARKETING', 2);     // dimensions in a group, level 2
 *
 * Use them with SubAnalysis::Required or SubAnalysis::Optional.
 */
final readonly class ChildValidation
{
    /**
     * Financial level of the dimension types Twinfield has, for dimensionType().
     */
    private const TYPE_LEVELS = [
        'BAS' => 1,
        'PNL' => 1,
        'DEB' => 2,
        'CRD' => 2,
        'KPL' => 2,
        'PRJ' => 3,
        'AST' => 3,
        'ACT' => 4,
    ];

    public function __construct(
        public ChildValidationType $type,
        /**
         * Financial level of the dimension: 1 balance sheet and profit and loss accounts, 2 e.g.
         * customers, suppliers and cost centers, 3 projects and fixed assets, 4 activities, or 0
         * (groups only).
         */
        public int $level,
        /** Dimension code, dimension type or dimension group code, depending on the type. */
        public string $value,
    ) {
        if ($level < 0 || $level > 4) {
            throw new InvalidArgumentException(sprintf('Child validation level must be 0 to 4, %d given.', $level));
        }

        if ($level === 0 && $type !== ChildValidationType::Group) {
            throw new InvalidArgumentException('Child validation level 0 is only allowed for a dimension group.');
        }

        XmlValue::code($value);
    }

    /**
     * Any dimension of this type, e.g. `KPL` for cost centers. The level follows from the type;
     * pass it for a type the SDK does not know.
     */
    public static function dimensionType(string $type, ?int $level = null): self
    {
        $level ??= self::TYPE_LEVELS[$type] ?? throw new InvalidArgumentException(sprintf(
            'Unknown dimension type "%s": pass its level, e.g. dimensionType(\'%s\', 2).',
            $type,
            $type,
        ));

        return new self(ChildValidationType::Type, $level, $type);
    }

    /**
     * Only this dimension, e.g. cost center `00100` on level 2.
     */
    public static function dimension(string $code, int $level): self
    {
        return new self(ChildValidationType::Code, $level, $code);
    }

    /**
     * Any dimension in this dimension group, e.g. `MARKETING`. Level 0 does not restrict the level.
     */
    public static function group(string $group, int $level = 0): self
    {
        return new self(ChildValidationType::Group, $level, $group);
    }

    /**
     * From a `<childvalidation>` element.
     *
     * @internal
     */
    public static function fromXml(XmlElement $validation): self
    {
        $value = $validation->text();

        if ($value === '') {
            throw $validation->invalid('a dimension code, type or group');
        }

        return new self(
            $validation->enumAttribute('type', ChildValidationType::class),
            $validation->intAttribute('level'),
            $value,
        );
    }
}
