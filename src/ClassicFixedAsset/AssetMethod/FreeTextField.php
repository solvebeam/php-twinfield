<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\ClassicFixedAsset\AssetMethod;

use InvalidArgumentException;
use SolveBeam\Twinfield\ProcessXml\XmlElement;

/**
 * One of the five free text fields of the fixed assets with this method, e.g. field 1
 * labelled "Serial number". The asset method sets the label and type; the value is on the asset.
 */
final readonly class FreeTextField
{
    /** Twinfield has five free text fields, 1 to 5. */
    public const int COUNT = 5;

    public function __construct(
        /** 1 to 5. */
        public int $id,
        public FreeTextType $type,
        /** Label of the field; null when the field is not used. */
        public ?string $label,
    ) {
        if ($id < 1 || $id > self::COUNT) {
            throw new InvalidArgumentException(sprintf('Free text field id must be 1 to 5, %d given.', $id));
        }

        if ($label === '') {
            throw new InvalidArgumentException('Free text field label must not be empty: use null for no label.');
        }
    }

    /**
     * From a `<freetext id="1" type="text">Serial number</freetext>` element.
     *
     * @internal
     */
    public static function fromXml(XmlElement $freeText): self
    {
        $id = $freeText->attribute('id');

        if (preg_match('~^[1-5]$~', $id) !== 1) {
            throw $freeText->invalid('a free text field with id 1 to 5');
        }

        return new self(
            (int) $id,
            $freeText->enumAttribute('type', FreeTextType::class),
            // An empty element when the field is not used.
            $freeText->text() === '' ? null : $freeText->text(),
        );
    }
}
