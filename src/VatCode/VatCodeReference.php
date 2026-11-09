<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\VatCode;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;
use SolveBeam\Twinfield\UnexpectedResponseException;

/**
 * The default VAT code of an account, with the name and type Twinfield adds, e.g.
 * `<vatcode name="BTW 21%" shortname="VH" type="sales" fixed="false">VH</vatcode>`.
 */
final readonly class VatCodeReference
{
    public function __construct(
        /** VAT code, e.g. `VH`. */
        public string $code,
        public string $name,
        /** Short name; null when not set. */
        public ?string $shortName,
        public VatCodeType $type,
    ) {
        if ($code === '' || $name === '') {
            throw new InvalidArgumentException('VAT code and name must not be empty.');
        }

        if ($shortName === '') {
            throw new InvalidArgumentException('VAT code short name must not be empty: use null for none.');
        }
    }

    /**
     * From the `<vatcode>` element; null when it is empty (`<vatcode fixed="false" />`), which
     * is how Twinfield returns an account without a default VAT code.
     */
    public static function fromXml(XmlElement $vatCode): ?self
    {
        if ($vatCode->text() === '') {
            return null;
        }

        $name = $vatCode->attribute('name');

        if ($name === '') {
            throw new UnexpectedResponseException(sprintf('%s/@name is empty.', $vatCode->path));
        }

        $shortName = $vatCode->attribute('shortname');

        return new self(
            $vatCode->text(),
            $name,
            $shortName === '' ? null : $shortName,
            $vatCode->enumAttribute('type', VatCodeType::class),
        );
    }
}
