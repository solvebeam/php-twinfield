<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Office;

use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * How the office shows dates and numbers in Twinfield (`regional`). The API itself always uses
 * `yyyymmdd` dates and `.` as decimal separator.
 */
final readonly class RegionalSettings
{
    public function __construct(
        /** E.g. `dd/mm/yyyy`. */
        public string $dateFormat,
        /** E.g. `.`. */
        public string $thousandsSeparator,
        /** E.g. `,`. */
        public string $decimalSeparator,
    ) {
    }

    /**
     * @internal
     */
    public static function fromXml(XmlElement $regional): self
    {
        return new self(
            dateFormat: $regional->string('dateformat'),
            thousandsSeparator: $regional->string('thousandsep'),
            decimalSeparator: $regional->string('decimalsep'),
        );
    }
}
