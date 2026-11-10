<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset;

use InvalidArgumentException;

/**
 * A setting of a fixed asset that Twinfield can lock (`fixedassets/*\/@locked`), e.g. the begin
 * period once the asset is active. The value is the element name.
 *
 *   if (!$asset->isLocked(ClassicFixedAssetField::DepreciateFrom)) {
 *       $input = $asset->edit()->depreciateFrom(Period::of(2026, 1));
 *   }
 */
enum ClassicFixedAssetField: string
{
    case Status = 'status';
    case ResidualValue = 'residualvalue';
    case StopValue = 'stopvalue';
    case Method = 'method';

    /** The period from which the asset is depreciated (`beginperiod`). */
    case DepreciateFrom = 'beginperiod';

    case PurchaseDate = 'purchasedate';
    case SellDate = 'selldate';
    case FreeText1 = 'freetext1';
    case FreeText2 = 'freetext2';
    case FreeText3 = 'freetext3';
    case FreeText4 = 'freetext4';
    case FreeText5 = 'freetext5';
    case TransactionLines = 'translines';

    /** Number of periods over which the asset is depreciated (`nrofperiods`). */
    case Periods = 'nrofperiods';

    case Percentage = 'percentage';
    case LastDepreciation = 'lastdepreciation';

    /**
     * Free text field 1 to 5.
     */
    public static function freeText(int $id): self
    {
        return self::tryFrom('freetext' . $id)
            ?? throw new InvalidArgumentException(sprintf('Free text field id must be 1 to 5, %d given.', $id));
    }

    /**
     * Name for messages, e.g. `begin period`.
     */
    public function label(): string
    {
        return match ($this) {
            self::Status => 'status',
            self::ResidualValue => 'residual value',
            self::StopValue => 'stop value',
            self::Method => 'asset method',
            self::DepreciateFrom => 'begin period',
            self::PurchaseDate => 'purchase date',
            self::SellDate => 'sell date',
            self::FreeText1, self::FreeText2, self::FreeText3, self::FreeText4, self::FreeText5 => sprintf(
                'free text %s',
                substr($this->value, -1),
            ),
            self::TransactionLines => 'transaction lines',
            self::Periods => 'number of periods',
            self::Percentage => 'percentage',
            self::LastDepreciation => 'last depreciation',
        };
    }
}
